<?php

class GT_Speed_Model_Observer
{

    /**
     * Enable/disable configuration
     */
    const XML_PATH_CRON_ENABLED = 'gtspeed/cron/enabled';

    /**
     * Cronjob expression configuration
     */
    const XML_PATH_CRON_EXPR = 'crontab/jobs/gtspeed_optimize_images/schedule/cron_expr';

    /**
     * Error email template configuration
     */
    const XML_PATH_ERROR_TEMPLATE  = 'gtspeed/cron/error_email_template';

    /**
     * Error email identity configuration
     */
    const XML_PATH_ERROR_IDENTITY  = 'gtspeed/cron/error_email_identity';

    /**
     * 'Send error emails to' configuration
     */
    const XML_PATH_ERROR_RECIPIENT = 'gtspeed/cron/error_email';

    /**
     * Main cronjob function
     *
     * Runs reindex, optimize and calculates stats
     *
     * @return this
     */
    public function process()
    {
        $errors = array();

        if (!Mage::getStoreConfigFlag(self::XML_PATH_CRON_ENABLED)) {
            return;
        }

        try {
            Mage::Helper('gtspeed/optimize')->Reindex();
            Mage::Helper('gtspeed/optimize')->Optimize();
            Mage::Helper('gtspeed/optimize')->calcStats();
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        if ($errors && Mage::getStoreConfig(self::XML_PATH_ERROR_RECIPIENT)) {
            $translate = Mage::getSingleton('core/translate');
            /* @var $translate Mage_Core_Model_Translate */
            $translate->setTranslateInline(false);

            $emailTemplate = Mage::getModel('core/email_template');
            /* @var $emailTemplate Mage_Core_Model_Email_Template */
            $emailTemplate->setDesignConfig(array('area' => 'backend'))
                ->sendTransactional(
                    Mage::getStoreConfig(self::XML_PATH_ERROR_TEMPLATE),
                    Mage::getStoreConfig(self::XML_PATH_ERROR_IDENTITY),
                    Mage::getStoreConfig(self::XML_PATH_ERROR_RECIPIENT),
                    null,
                    array('warnings' => join("\n", $errors))
                );

            $translate->setTranslateInline(true);
        }
        return $this;
    }
}
