<?php
 
class GT_Speed_Model_Mysql4_Image extends Mage_Core_Model_Mysql4_Abstract
{
    // primary key is not auto increment
    protected $_isPkAutoIncrement = false;

    public function _construct()
    {   
        $this->_init('gtspeed/image', 'image_id');
    }
}
