<?php

/**
 * Html page block
 *
 * @category   Mage
 * @package    Mage_Page
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class GT_Speed_Block_Page_Html_Head extends Mage_Page_Block_Html_Head {

    /**
     * Get HEAD HTML with CSS/JS/RSS definitions
     * (actually it also renders other elements, TODO: fix it up or rename this method)
     *
     * @return string
     */
    public function getCssJsHtml() {

        // separate items by types
        $lines = array( );
        foreach ( $this->_data['items'] as $item ) {
            if ( !is_null( $item['cond'] ) && !$this->getData( $item['cond'] ) || !isset( $item['name'] ) ) {
                continue;
            }
            $if = !empty( $item['if'] ) ? $item['if'] : '';
            $params = !empty( $item['params'] ) ? $item['params'] : '';
            switch ( $item['type'] ) {
                case 'js':        // js/*.js
                case 'skin_js':   // skin/*/*.js
                case 'js_css':    // js/*.css
                case 'skin_css':  // skin/*/*.css
                    $lines[$if][$item['type']][$params][$item['name']] = $item['name'];
                    break;
                default:
                    $this->_separateOtherHtmlHeadElements( $lines, $if, $item['type'], $params, $item['name'], $item );
                    break;
            }
        }

        // prepare HTML
        $minifyCss = Mage::getStoreConfigFlag( 'gtspeed/cssjs/min_css' );
        $minifyJs = Mage::getStoreConfigFlag( 'gtspeed/cssjs/min_js' );
        // make sure we can only merge when minify is on
        $shouldMergeJs = ($minifyJs AND Mage::getStoreConfigFlag( 'gtspeed/cssjs/merge_js' ));
        $shouldMergeCss = ($minifyCss AND Mage::getStoreConfigFlag( 'gtspeed/cssjs/merge_css' ));
        $html = '';
        // add GTspeed promotional
        if ( $minifyCss OR $minifyJs ) {
            $html .= '<!-- Optimized using GTspeed -->' . "\n";
        }



        foreach ( $lines as $if => $items ) {
            if ( empty( $items ) ) {
                continue;
            }
            if ( !empty( $if ) ) {
                $html .= '<!--[if ' . $if . ']>' . "\n";
            }

            // static and skin css
            $html .= $this->_prepareStaticAndSkinElements( '<link rel="stylesheet" type="text/css" href="%s"%s />' . "\n", empty( $items['js_css'] ) ? array( ) : $items['js_css'], empty( $items['skin_css'] ) ? array( ) : $items['skin_css'], $shouldMergeCss ? array( $this, 'getMergedCssUrl' ) : null, ( bool ) $minifyCss
            );

            // static and skin javascripts
            $html .= $this->_prepareStaticAndSkinElements( '<script type="text/javascript" src="%s"%s></script>' . "\n", empty( $items['js'] ) ? array( ) : $items['js'], empty( $items['skin_js'] ) ? array( ) : $items['skin_js'], $shouldMergeJs ? array( $this, 'getMergedJsUrl' ) : null, ( bool ) $minifyJs
            );

            // other stuff
            if ( !empty( $items['other'] ) ) {
                $html .= $this->_prepareOtherHtmlHeadElements( $items['other'] ) . "\n";
            }

            if ( !empty( $if ) ) {
                $html .= '<![endif]-->' . "\n";
            }
        }
        // add GTspeed promotional
        if ( $minifyCss OR $minifyJs ) {
            $html .= '<!-- Optimized using GTspeed -->' . "\n";
        }

        return $html;
    }

    /**
     * Merge static and skin files of the same format into 1 set of HEAD directives or even into 1 directive
     *
     * Will attempt to merge into 1 directive, if merging callback is provided. In this case it will generate
     * filenames, rather than render urls.
     * The merger callback is responsible for checking whether files exist, merging them and giving result URL
     *
     * @param string $format - HTML element format for sprintf('<element src="%s"%s />', $src, $params)
     * @param array $staticItems - array of relative names of static items to be grabbed from js/ folder
     * @param array $skinItems - array of relative names of skin items to be found in skins according to design config
     * @param callback $mergeCallback
     * @param bool $minify - minify this file type
     * @return string
     */
    protected function &_prepareStaticAndSkinElements( $format, array $staticItems, array $skinItems, $mergeCallback = null, $minify = false ) {
        $designPackage = Mage::getDesign();
        $baseJsUrl = Mage::getBaseUrl( 'js' );
        $items = array( );
        if ( $mergeCallback && !is_callable( $mergeCallback ) ) {
            $mergeCallback = null;
        }

        // get static files from the js folder, no need in lookups
        foreach ( $staticItems as $params => $rows ) {
            foreach ( $rows as $name ) {
                $items[$params][] = $mergeCallback ? Mage::getBaseDir() . '/js/' . $name : $baseJsUrl . $name;
            }
        }

        // lookup each file basing on current theme configuration
        foreach ( $skinItems as $params => $rows ) {
            foreach ( $rows as $name ) {
                $items[$params][] = $mergeCallback ? Mage::getBaseDir() . substr( $this->getSkinUrl( $name ), strpos( $this->getSkinUrl( $name ), '/skin' ) ) 
                    : Mage::getBaseDir() .$designPackage->getSkinUrl( $name, array( ) );
            }
        }

        $baseUrl = Mage::getBaseUrl( Mage_Core_Model_Store::URL_TYPE_WEB );
        $html = '';
        foreach ( $items as $params => $rows ) {

            // attempt to merge
            $mergedUrl = false;
            if ( $mergeCallback ) {
                $mergedUrl = call_user_func( $mergeCallback, $rows );
            }
            // render elements
            $params = trim( $params );
            $params = $params ? ' ' . $params : '';
            if ( $mergedUrl ) {
                $html .= sprintf( $format, $mergedUrl, $params );
            } else {
                foreach ( $rows as $src ) {
                    // minify each file
                    if ( $minify ) {
                        $build = Mage::getModel( 'gtspeed/build' )->__construct( $src );
                        //$src = str_replace($baseUrl, $baseUrl."min/?f=", $build->uri($src, true));
                    }
                    $html .= sprintf( $format, $src, $params );
                }
            }
        }
        return $html;
    }

    /**
     * Custom merger callback for minify library
     *
     * Processes an array of files through the Minify/Build class and returns a minify library URL to the minifed and combined file.
     *
     * @param array $files - array of files to be merged.
     * @return string
     */
    public function getMergedUrl( $files, $type ) {
        if ( Mage::app()->getStore()->isCurrentlySecure() ) {
            $baseUrl = Mage::getStoreConfig( 'gtspeed/secure/base_' . $type . '_url' );
        } else {
            $baseUrl = Mage::getStoreConfig( 'gtspeed/unsecure/base_' . $type . '_url' );
        }

        $baseDirs = array(
            Mage::getBaseDir(),
            Mage::getBaseUrl( Mage_Core_Model_Store::URL_TYPE_SKIN ),
            Mage::getBaseUrl( Mage_Core_Model_Store::URL_TYPE_JS )
        );

        $build = Mage::getModel( 'gtspeed/build' )->__construct( $files );

        foreach ( $files as $i => $src ) {
            $files[$i] = str_replace( $baseDirs, '', $src );
        }

        $url = join( ",", $files );

        return $baseUrl . "min/?f=" . $build->uri( $url, true );
    }

    /**
     * Alias for getMergedUrl
     */
    public function getMergedCssUrl( $files ) {
        return $this->getMergedUrl( $files, 'skin' );
    }

    /**
     * Alias for getMergedUrl
     */
    public function getMergedJsUrl( $files ) {
        return $this->getMergedUrl( $files, 'js' );
    }

}
