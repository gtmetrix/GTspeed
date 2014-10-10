<?php

class GT_Speed_Model_Stat extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('gtspeed/stat');
    }

    /**
     * Load a stat by its name
     *
     * @param string $value - stat name
     * @return stat object model or false if not found
     */
    public function loadByName($value) 
    {
        $collection = $this->getResourceCollection()
            ->addNameFilter($value)
            ->setPageSize(1)
            ->setCurPage(1);

        foreach ($collection as $object) {
            return $object;
        }
        return false;
    }
}
