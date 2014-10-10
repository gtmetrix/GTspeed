<?php

class GT_Speed_Helper_Optimize extends Mage_Core_Helper_Abstract {

    /**
     * Optimizes all unoptimized images
     *
     * Returns an array containing final stats
     *
     * @return array
     */
    public function Optimize() {
        $collection = Mage::getModel( 'gtspeed/image' )->getCollection()->addOptimizedFilter( 0 );

        $count = 0;
        $o_filesize = 0;
        $n_filesize = 0;
        foreach ( $collection as $item ) {
            $filepath = realpath( $item->getFilepath() );
            // if image exists, optimize. else remove it from database
            if ( file_exists( $filepath ) ) {
                $item->setOfilesize( filesize( $filepath ) );
                $o_filesize += filesize( $filepath );
                $this->_optimizeFile( $item );
                $item->setLastoptmtime( filemtime( $filepath ) );
                $item->setOptimized( 1 );
                $item->save();
                $count++;
            } else {
                $item->delete();
            }
        }

        // fail save to update image stats, since file stat data (mtime etc) takes a split second to update
        // first run may not have catched the update
        foreach ( $collection as $item ) {
            if ( $item->getOptimized() ) {
                $filepath = realpath( $item->getFilepath() );
                $item->setLastoptmtime( filemtime( $filepath ) );
                $item->setNfilesize( filesize( $filepath ) );
                $n_filesize += filesize( $filepath );
                $item->save();
            }
        }

        return array(
            'count' => $count,
            'reduced' => ($o_filesize - $n_filesize),
            'percent' => round( (1 - ($n_filesize / $o_filesize)) * 100, 2 )
        );
    }

    /**
     * Optimizes a batch (100) of unoptimized images
     *
     * Returns an array containing intermediate stats
     *
     * @return array
     */
    public function OptimizeByIndex( $index ) {
        $collection = Mage::getModel( 'gtspeed/image' )->getCollection()->addOptimizedFilter( 0 )->setPageSize( 100 )->setCurPage( 1 );

        $count = 0;
        $o_filesize = 0;
        $n_filesize = 0;
        foreach ( $collection as $item ) {
            $filepath = realpath( $item->getFilepath() );
            // if image exists, optimize. else remove it from database
            if ( file_exists( $filepath ) ) {
                $item->setOfilesize( filesize( $filepath ) );
                $o_filesize += filesize( $filepath );
                $this->_optimizeFile( $item );
                $item->setLastoptmtime( filemtime( $filepath ) );
                $item->setOptimized( 1 );
                $item->save();
                $count++;
            } else {
                $item->delete();
            }
        }

        // fail save to update image stats, since file stat data (mtime etc) takes a split second to update
        // first run may not have catched the update
        foreach ( $collection as $item ) {
            if ( $item->getOptimized() ) {
                $filepath = realpath( $item->getFilepath() );
                $item->setLastoptmtime( filemtime( $filepath ) );
                $item->setNfilesize( filesize( $filepath ) );
                $n_filesize += filesize( $filepath );
                $item->save();
            }
        }

        return array(
            'count' => $count,
            'o_filesize' => $o_filesize,
            'n_filesize' => $n_filesize
        );
    }

    /**
     * Reindex all file paths
     *
     * Returns number of files processed
     *
     * @return int
     */
    public function Reindex() {
        // update existing database items
        $collection = Mage::getModel( 'gtspeed/image' )->getCollection();

        foreach ( $collection as $item ) {
            $filepath = realpath( $item->getFilepath() );
            // remove image if it no longer exists
            if ( file_exists( $filepath ) ) {
                // reset optimized if file changed
                if ( filemtime( $filepath ) != $item->getLastoptmtime() ) {
                    $item->setOptimized( 0 );
                    $item->save();
                }
            } else {
                $item->delete();
            }
        }

        $paths = Mage::getStoreConfig( 'gtspeed/imgpath/paths' );
        $paths = explode( ",", $paths );

        $count = 0;

        foreach ( $paths as $path ) {
            $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( Mage::getBaseDir() . DS . $path, RecursiveDirectoryIterator::FOLLOW_SYMLINKS ) );

            foreach ( $iterator as $filename => $file ) {
                // get image files
                if ( $file->isFile() && preg_match( '/^.+\.(jpe?g|gif|png)$/i', $file->getFilename() ) ) {
                    $filepath = $file->getRealPath();
                    if ( !is_writable( $filepath ) ) {
                        continue;
                    }

                    $item = Mage::getModel( 'gtspeed/image' );
                    $item->setId( md5( $filepath ) );
                    $item->setFilepath( $filepath );
                    $item->save();

                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Reindex a single file path based on the paths in the configuration setting 'gtspeed/imgpath/paths'
     *
     * Also stores an array containing the filepaths of non-writable images to an admin session setting
     *
     * Returns number of files processed
     *
     * @return int
     */
    public function ReindexByIndex( $index ) {
        $paths = Mage::getStoreConfig( 'gtspeed/imgpath/paths' );
        $paths = explode( ",", $paths );

        $path = $paths[$index];

        $count = 0;
        // get not writables
        $nowrite = Mage::getSingleton( 'adminhtml/session' )->getNotWritable();
        if ( !is_array( $nowrite ) )
            $nowrite = array( );

        $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( Mage::getBaseDir() . DS . $path, RecursiveDirectoryIterator::FOLLOW_SYMLINKS ) );

        foreach ( $iterator as $filename => $file ) {
            // get image files
            if ( $file->isFile() && preg_match( '/^.+\.(jpe?g|gif|png)$/i', $file->getFilename() ) ) {
                $filepath = $file->getRealPath();
                if ( !is_writable( $filepath ) ) {
                    $nowrite[] = $filepath;
                    continue;
                }

                $item = Mage::getModel( 'gtspeed/image' );
                $item->setId( md5( $filepath ) );
                $item->setFilepath( $filepath );
                $item->save();

                $count++;
            }
        }

        // set not writables
        Mage::getSingleton( 'adminhtml/session' )->setNotWritable( $nowrite );

        return $count;
    }

    /**
     * Updates existing images in the database
     *
     * Returns number of database entries processed
     *
     * @return int
     */
    public function Update() {
        $collection = Mage::getModel( 'gtspeed/image' )->getCollection();

        $count = 0;
        foreach ( $collection as $item ) {
            $filepath = realpath( $item->getFilepath() );
            // remove image if it no longer exists
            if ( file_exists( $filepath ) ) {
                // reset optimized if file changed
                if ( filemtime( $filepath ) != $item->getLastoptmtime() ) {
                    $item->setOptimized( 0 );
                    $item->save();
                    $count++;
                }
            } else {
                $item->delete();
                $count++;
            }
        }

        return $count;
    }

    /**
     * Calculates and stores optimization statistics to the database
     */
    public function calcStats() {
        $collection = Mage::getModel( 'gtspeed/image' )->getCollection()->addOptimizedFilter( 1 );

        // image optimization stats
        $o_filesize = 0;
        $n_filesize = 0;

        foreach ( $collection as $item ) {
            $o_filesize += $item->getOfilesize();
            $n_filesize += $item->getNfilesize();
        }

        // filesize difference stat
        $diff = ceil( ($o_filesize - $n_filesize) / 1024 );

        $diff_stat = Mage::getModel( 'gtspeed/stat' )->loadByName( 'image_opt' );

        if ( !$diff_stat ) {
            $diff_stat = Mage::getModel( 'gtspeed/stat' );
            $diff_stat->setStatName( 'image_opt' );
        }

        $diff_stat->setStat( $diff );
        $diff_stat->save();

        // percentage difference stat
        $percent = round( (1 - ($n_filesize / $o_filesize)) * 100, 2 );

        $pct_stat = Mage::getModel( 'gtspeed/stat' )->loadByName( 'image_opt_pct' );

        if ( !$pct_stat ) {
            $pct_stat = Mage::getModel( 'gtspeed/stat' );
            $pct_stat->setStatName( 'image_opt_pct' );
        }

        $pct_stat->setStat( $percent );
        $pct_stat->save();
    }

    /**
     * Run optimization utility on an image
     *
     * @param GT_Speed_Model_Image $object - The image's model object
     */
    protected function _optimizeFile( GT_Speed_Model_Image $object ) {


        $path = realpath( $object->getFilepath() );
        $info = pathinfo( $path );

        $output = array( );
        switch ( $info['extension'] ) {
            case 'jpg':
            case 'jpeg':

                exec( Mage::helper( 'gtspeed' )->getJpgUtil( $path ), $output, $return_var );
                $type = 'jpg';
                break;
            case 'png':
                exec( Mage::helper( 'gtspeed' )->getPngUtil( $path ), $output, $return_var );
                $type = 'png';
                break;
            case 'gif':
                exec( Mage::helper( 'gtspeed' )->getGifUtil( $path ), $output, $return_var );
                $type = 'gif';
                break;
        }

        if ( 126 == $return_var ) {
            $error = Mage::getStoreConfig( 'gtspeed/imgopt/' . $type . 'util' ) . ' is not executable';
            Mage::log( $error, null, "gtspeed.log" );
            Mage::getSingleton( 'adminhtml/session' )->addError( Mage::helper( 'adminhtml' )->__( $error ) );
            Mage::app()
                    ->getResponse()
                    ->setRedirect( '*/*/' );
            Mage::app()
                    ->getResponse()
                    ->sendResponse();
            exit;
        } else {
            if ( Mage::getStoreConfigFlag( 'gtspeed/imgdebug/imgoutput' ) )
                Mage::log( $output, null, "gtspeed.log" );
            return true;
        }
    }

}
