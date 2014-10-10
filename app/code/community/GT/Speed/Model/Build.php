<?php

set_include_path(get_include_path().PATH_SEPARATOR.Mage::getBaseDir().DS."min".DS."lib");
require_once('Minify/Build.php');

class GT_Speed_Model_Build extends Minify_Build
{
    /**
     * Create a build object
     * 
     * @param array $sources array of Minify_Source objects and/or file paths
     * 
     * @return this
     */
    public function __construct($sources)
    {
  
        $baseUrls = array(
            Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB),
           // Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN),
           // Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_JS)
        );
        
        $max = 0;
        foreach ((array)$sources as $source) {
            if ($source instanceof Minify_Source) {
                $max = max($max, $source->lastModified);
            } elseif (is_string($source)) {

                $source = str_replace($baseUrls, '//', $source);

                if (0 === strpos($source, '//')) {
                    $source = $_SERVER['DOCUMENT_ROOT'] . substr($source, 1);
                }
                if (is_file($source)) {
                    $max = max($max, filemtime($source));
                }
            }
        }
        $this->lastModified = $max;

        return $this;
    }
}
?>
