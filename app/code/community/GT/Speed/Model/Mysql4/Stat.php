<?php
 
class GT_Speed_Model_Mysql4_Stat extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {   
        $this->_init('gtspeed/stat', 'stat_id');
    }
}
