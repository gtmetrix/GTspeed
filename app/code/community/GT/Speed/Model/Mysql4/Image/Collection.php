<?php
 
class GT_Speed_Model_Mysql4_Image_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('gtspeed/image');
    }

    /**
     * Filter collection by optimized status
     *
     * @param int $optimized - optimized status
     * @return this
     */
    public function addOptimizedFilter($optimized) 
    {
        $this->getSelect()
            ->where('optimized = ?', $optimized);
        return $this;
    }

}
