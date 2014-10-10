<?php

class GT_Speed_Helper_Data extends Mage_Core_Helper_Abstract {

    protected $_utilpath = "";
    protected $_utilext = "";

    /**
     * Checks if server OS is windows
     *
     * @return bool
     */
    protected function _getIsWindows() 
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * _getUtil() aliases
     */
    public function getJpgUtil($filepath) 
    {
        return $this->_getUtil('jpg', $filepath);
    }

    public function getGifUtil($filepath) 
    {
        return $this->_getUtil('gif', $filepath);
    }

    public function getPngUtil($filepath) 
    {
        return $this->_getUtil('png', $filepath);
    }

    /**
     * Formats and returns the shell command string for an image optimization utility
     *
     * @param string $type - image type. Valid values gif|jpg|png
     * @param string $filepath - path to the image to be optimized
     * @return string
     */
    protected function _getUtil($type, $filepath) 
    {
        $cmd = $this->_getUtilPath() . DS . Mage::getStoreConfig('gtspeed/imgopt/'.$type.'util') . $this->_getUtilExt() . " " . Mage::getStoreConfig('gtspeed/imgopt/'.$type.'utilopt') . " " . $filepath;
        return $cmd;
    }

    /**
     * Gets and stores path to utilities
     *
     * Checks server OS to determine the path to where the image optimization utilities are stored.
     *
     * @return string
     */
    protected function _getUtilPath() 
    {
        if (empty($this->_utilpath) || empty($this->_utilext)) {
            $this->_utilext = $this->_getIsWindows() ? ".exe" : "";

            $os = $this->_getIsWindows() ? "win32" : "elf32";
            $this->_utilpath = Mage::getBaseDir('lib') . DS . "gtspeed" . DS . $os;
        }

        return $this->_utilpath;
    }

    /**
     * Gets and stores utility extensions
     *
     * Checks server OS to determine the utility extensions.
     *
     * @return string
     */
    protected function _getUtilExt() 
    {
        if (empty($this->_utilpath) || empty($this->_utilext)) {
            $this->_utilext = $this->_getIsWindows() ? ".exe" : "";

            $os = $this->_getIsWindows() ? "win32" : "elf32";
            $this->_utilpath = Mage::getBaseDir('lib') . DS . "gtspeed" . DS . $os;
        }

        return $this->_utilext;
    }
}
