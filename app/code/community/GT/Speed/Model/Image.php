<?php

class GT_Speed_Model_Image extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('gtspeed/image');
    }
}
