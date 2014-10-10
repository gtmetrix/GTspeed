<?php

class GT_Speed_Model_System_Config_Backend_Cron extends Mage_Core_Model_Config_Data
{
    const CRON_STRING_PATH  = 'crontab/jobs/gtspeed_optimize_images/schedule/cron_expr';
    const CRON_MODEL_PATH   = 'crontab/jobs/gtspeed_optimize_images/run/model';

    /**
     * _afterSave() for gtspeed/cron/frequency
     *
     * Sets crontab/jobs/gtspeed_optimize_images/schedule/cron_expr to the specified settings in the configuration
     */
    protected function _afterSave()
    {
        $enabled     = $this->getData('groups/cron/fields/enabled/value');
        $frequncy    = $this->getData('groups/cron/fields/frequency/value');
        $time        = $this->getData('groups/cron/fields/time/value');

        $frequencyDaily     = Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_DAILY;
        $frequencyWeekly    = Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY;
        $frequencyMonthly   = Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY;
        $cronDayOfWeek      = date('N');

        $cronExprArray      = array(
            intval($time[1]),                                   # Minute
            intval($time[0]),                                   # Hour
            ($frequncy == $frequencyMonthly) ? '1' : '*',       # Day of the Month
            '*',                                                # Month of the Year
            ($frequncy == $frequencyWeekly) ? '1' : '*',         # Day of the Week
        );

        $cronExprString = join(' ', $cronExprArray);

        try {
            Mage::getModel('core/config_data')
                ->load(self::CRON_STRING_PATH, 'path')
                ->setValue($cronExprString)
                ->setPath(self::CRON_STRING_PATH)
                ->save();
            Mage::getModel('core/config_data')
                ->load(self::CRON_MODEL_PATH, 'path')
                ->setValue((string) Mage::getConfig()->getNode(self::CRON_MODEL_PATH))
                ->setPath(self::CRON_MODEL_PATH)
                ->save();
            Mage::getConfig()->cleanCache();
        } catch (Exception $e) {
            throw new Exception(Mage::helper('cron')->__('Unable to save the cron expression.'));
        }
    }
}

