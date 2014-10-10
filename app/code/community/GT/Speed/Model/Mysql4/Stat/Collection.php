<?php
 
class GT_Speed_Model_Mysql4_Stat_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('gtspeed/stat');
    }

    /**
     * Filter the collection by stat_name
     *
     * @param string $name - the stat_name
     * @return this
     */
    public function addNameFilter($name) 
    {
        $this->getSelect()
            ->where('stat_name = ?', $name);
        return $this;
    }
}
