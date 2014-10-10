<?php

class GT_Speed_InfoController extends Mage_Adminhtml_Controller_Action {

    protected function _initAction() {
        $this->loadLayout()
                ->_setActiveMenu( 'system/gt' );

        return $this;
    }

    public function indexAction() {
        $this->_initAction()
                ->renderLayout();
    }

    /**
     * Processes a batch AJAX request
     *
     * The method executes the requested action and returns a JSON array containing the action's progress and next action
     * back to the browser.
     *
     * The requested action is passed through the 'do' parameter. Valid actions are: "update", "reindex" and "optimize." 
     * The "update" action will follow through to "reindex," so only "update" and "optimize" are used in the admin interface.
     *
     * Returns a JSON array containing two keys back to the browser. These are:
     *
     * 'progress' - a string containing the progress message to be displayed by the AJAX box in the admin interface
     * 'next' - a string containing the next internal action of the batch action. Browser will recursively pass this action back
     *          into this method to continue progress. The string "done" will stop this process and finish the batch action.
     *          For example, "reindex" will return a 'next' of "reindex1", which gets AJAX'd back into this method, executed
     *          and returns a 'next' of "reindex2" and so on. The number is used to note that the action is not in the first
     *          iteration for stat keeping purposes.
     *
     * This design is required because running a controller action will completely block the entire admin session, meaning
     * that we cannot get intermediate progress information while the action is still running.
     */
    public function batchAction() {
        // get params
        $params = $this->getRequest()->getParams();
        $action = $params['do'];

        $result = array( );
        if ( $action == "update" ) {
            try {
                // run update
                $count = Mage::Helper( 'gtspeed/optimize' )->Update();
                if ( $count == 0 ) {
                    // no files updated
                    $result['progress'] = "All existing images are up to date. ";
                } else {
                    $result['progress'] = "$count images updated. ";
                }
                // follow thru to "reindex"
                $result['progress'] .= "Scanning for new images...";
                $result['next'] = "reindex";
            } catch (Exception $e) {
                Mage::getSingleton( 'adminhtml/session' )->addError( $e->getMessage() );
                $result['next'] = "done";
            }
        } else if ( strpos( $action, "reindex" ) !== false ) {
            $index = str_replace( "reindex", "", $action );
            $index = intval( $index );

            // get indexed paths to make sure $index number isn't out of bounds, since each folder scan is an uninterruptable operation
            $paths = Mage::getStoreConfig( 'gtspeed/imgpath/paths' );
            $paths = explode( ",", $paths );
            $num_paths = count( $paths );

            if ( $index >= $num_paths )
                return;

            try {
                // run reindex on $paths[$index] folder
                $count = $this->_reindexBatch( $index );

                $result['progress'] = "Scanned $count files...";
                // we're done after we've indexed the last path, else continue onto the next path
                if ( $index == ($num_paths - 1) ) {
                    $nowrite = Mage::getSingleton( 'adminhtml/session' )->getNotWritable();
                    if ( is_array( $nowrite ) && count( $nowrite ) > 0 ) {
                        // found unwritable files, show them as errors
                        Mage::getSingleton( 'adminhtml/session' )->addError( Mage::helper( 'adminhtml' )->__( 'The following images may not have sufficient write permissions. Please ensure that the server has sufficient write permissions to these images in order for them to be optimized.' ) );
                        foreach ( $nowrite as $file ) {
                            Mage::getSingleton( 'adminhtml/session' )->addError( $file );
                        }
                        Mage::getSingleton( 'adminhtml/session' )->setNotWritable( array( ) );
                    }

                    // update GTspeed stats
                    Mage::helper( 'gtspeed/optimize' )->calcStats();
                    $result['next'] = "done";
                    $msg = "Update complete! Updated $count files.";
                    Mage::getSingleton( 'adminhtml/session' )->addSuccess( Mage::helper( 'adminhtml' )->__( $msg ) );
                } else {
                    $result['next'] = "reindex" . ($index + 1);
                }
            } catch (Exception $e) {
                Mage::getSingleton( 'adminhtml/session' )->addError( $e->getMessage() );
                $result['next'] = "done";
            }
        } else if ( strpos( $action, "optimize" ) !== false ) {
            $index = str_replace( "optimize", "", $action );
            $index = intval( $index );

            try {
                // run "optimize"
                $results = $this->_optimizeBatch( $index );

                if ( $results['run'] ) {
                    $result['progress'] = "Optimized " . $results['count'] . " of " . $results['total'] . " images...";
                    $result['next'] = "optimize" . ($index + 1);
                } else {
                    // update GTspeed stats
                    Mage::helper( 'gtspeed/optimize' )->calcStats();
                    $result['next'] = "done";
                    $msg = "Optimization complete! " . $results['count'] . " images successfully optimized. File sizes reduced by " . $results['reduced'] . " bytes (" . $results['percent'] . "%).";
                    Mage::getSingleton( 'adminhtml/session' )->addSuccess( Mage::helper( 'adminhtml' )->__( $msg ) );
                }
            } catch (Exception $e) {
                Mage::getSingleton( 'adminhtml/session' )->addError( $e->getMessage() );
                $result['next'] = "done";
            }
        }

        echo Mage::Helper( 'core' )->jsonEncode( $result );
    }

    /**
     * Run one batch of optimization and process stats
     *
     * @param int $index - current iteration
     * @return array
     */
    protected function _optimizeBatch( $index ) {
        $num_items = 0;
        $count = 0;
        $o_filesize = 0;
        $n_filesize = 0;

        // set stats to admin session if this is the first iteration, else load stats
        if ( $index == 0 ) {
            $num_items = Mage::getModel( 'gtspeed/image' )->getCollection()->addOptimizedFilter( 0 )->getSize();
            Mage::getSingleton( 'adminhtml/session' )->setProgressCount( 0 );
            Mage::getSingleton( 'adminhtml/session' )->setProgressTotalCount( $num_items );
            Mage::getSingleton( 'adminhtml/session' )->setOrigFileSize( 0 );
            Mage::getSingleton( 'adminhtml/session' )->setNewFileSize( 0 );
        } else {
            $count = Mage::getSingleton( 'adminhtml/session' )->getProgressCount();
            $o_filesize = Mage::getSingleton( 'adminhtml/session' )->getOrigFileSize();
            $n_filesize = Mage::getSingleton( 'adminhtml/session' )->getNewFileSize();
            $num_items = Mage::getSingleton( 'adminhtml/session' )->getProgressTotalCount();
        }

        // run if we haven't finished, else return final stats
        if ( $count < $num_items ) {
            // run 1 batch and update stats
            $results = Mage::helper( 'gtspeed/optimize' )->OptimizeByIndex( $index );
            $count += $results['count'];
            $o_filesize += $results['o_filesize'];
            $n_filesize += $results['n_filesize'];

            Mage::getSingleton( 'adminhtml/session' )->setProgressCount( $count );
            Mage::getSingleton( 'adminhtml/session' )->setOrigFileSize( $o_filesize );
            Mage::getSingleton( 'adminhtml/session' )->setNewFileSize( $n_filesize );

            return array(
                'run' => 1,
                'count' => $count,
                'o_filesize' => $o_filesize,
                'n_filesize' => $n_filesize,
                'total' => $num_items
            );
        } else {
            return array(
                'run' => 0,
                'count' => $count,
                'total' => $num_items,
                'reduced' => ($o_filesize - $n_filesize),
                'percent' => round( (1 - ($n_filesize / $o_filesize)) * 100, 2 )
            );
        }
    }

    /**
     * Index one folder and process stats
     *
     * Code to index one folder is uninterruptible, so process stats after each folder
     *
     * @param int $index - current iteration
     * @return int
     */
    protected function _reindexBatch( $index ) {
        $count = 0;
        if ( $index == 0 ) {
            Mage::getSingleton( 'adminhtml/session' )->setProgressCount( 0 );
            Mage::getSingleton( 'adminhtml/session' )->setNotWritable( array( ) );
        } else {
            $count = Mage::getSingleton( 'adminhtml/session' )->getProgressCount();
        }

        $count += Mage::helper( 'gtspeed/optimize' )->ReindexByIndex( $index );

        Mage::getSingleton( 'adminhtml/session' )->setProgressCount( $count );

        return $count;
    }

    /**
     * Aliases for _flipConfig()
     */
    public function mincssAction() {
        $this->_flipConfig( 'gtspeed/cssjs/min_css' );
    }

    public function minjsAction() {
        $this->_flipConfig( 'gtspeed/cssjs/min_js' );
    }

    public function mergecssAction() {
        $this->_flipConfig( 'gtspeed/cssjs/merge_css' );
    }

    public function mergejsAction() {
        $this->_flipConfig( 'gtspeed/cssjs/merge_js' );
    }

    public function expiresAction() {
        $this->_flipConfig( 'gtspeed/expires/enabled', 'gtspeed/system_config_backend_expires' );
    }

    /**
     * Gets a store config, and sets and saves it as the opposite setting.
     *
     * For use with yes/no settings only
     *
     * @param string $path - configuration path
     * @param string $model - configuration model. Change only if a custom _afterSave() needs to be ran for the
     *                        configuration.
     */
    protected function _flipConfig( $path, $model = 'core/config_data' ) {

        // Check var/mincache and min are writable
        if ( 'gtspeed/cssjs/min_css' == $path || 'gtspeed/cssjs/min_js' == $path ) {
            if ( !is_writable( Mage::getBaseDir( 'var' ) . DIRECTORY_SEPARATOR . 'mincache' ) ) {
                Mage::getSingleton( 'adminhtml/session' )->addError( Mage::helper( 'adminhtml' )->__( 'var/mincache folder is not writable! var/mincache must be writable to enable Minification.' ) );
                $this->_redirect( '*/*/' );
                return;
            }
            if ( !is_writable( Mage::getBaseDir() . DIRECTORY_SEPARATOR . 'min' ) ) {
                Mage::getSingleton( 'adminhtml/session' )->addError( Mage::helper( 'adminhtml' )->__( 'min folder is not writable! min must be writable to enable Minification.' ) );
                $this->_redirect( '*/*/' );
                return;
            }
        }

        $value = Mage::getStoreConfig( $path );

        try {
            Mage::getModel( $model )
                    ->load( $path, 'path' )
                    ->setValue( intval( !$value ) )
                    ->setPath( $path )
                    ->save();
            Mage::getConfig()->cleanCache();
            Mage::getSingleton( 'adminhtml/session' )->addSuccess( Mage::helper( 'adminhtml' )->__( 'Setting successfully saved!' ) );
            $this->_redirect( '*/*/' );
        } catch (Exception $e) {
            Mage::getSingleton( 'adminhtml/session' )->addError( $e->getMessage() );
            $this->_redirect( '*/*/' );
        }
    }

    /**
     * Process scheduled task form in admin interface
     *
     * Processes form and sets the cronjob. Same functionality as in System > Configuration
     */
    public function scheduleAction() {
        if ( $this->getRequest()->getPost() ) {
            try {
                // get post data
                $post = $this->getRequest()->getPost();
                $ENABLED_PATH = 'gtspeed/cron/enabled';

                // post data exists
                if ( count( $post ) > 2 ) {
                    $CRON_STRING_PATH = 'crontab/jobs/gtspeed_optimize_images/schedule/cron_expr';
                    $CRON_MODEL_PATH = 'crontab/jobs/gtspeed_optimize_images/run/model';

                    $FREQUENCY_PATH = 'gtspeed/cron/frequency';
                    $TIME_PATH = 'gtspeed/cron/time';

                    $time = $post['time'];
                    $frequency = $post['frequency'];

                    // save the settings
                    Mage::getModel( 'core/config_data' )
                            ->load( $ENABLED_PATH, 'path' )
                            ->setValue( 1 )
                            ->setPath( $ENABLED_PATH )
                            ->save();
                    Mage::getModel( 'core/config_data' )
                            ->load( $FREQUENCY_PATH, 'path' )
                            ->setValue( $frequency )
                            ->setPath( $FREQUENCY_PATH )
                            ->save();
                    Mage::getModel( 'core/config_data' )
                            ->load( $TIME_PATH, 'path' )
                            ->setValue( implode( ',', $time ) )
                            ->setPath( $TIME_PATH )
                            ->save();

                    // update cron settings
                    $frequencyDaily = Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_DAILY;
                    $frequencyWeekly = Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY;
                    $frequencyMonthly = Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY;

                    $cronExprArray = array(
                        intval( $time[1] ), # Minute
                        intval( $time[0] ), # Hour
                        ($frequency == $frequencyMonthly) ? '1' : '*', # Day of the Month
                        '*', # Month of the Year
                        ($frequency == $frequencyWeekly) ? '1' : '*', # Day of the Week
                    );

                    $cronExprString = join( ' ', $cronExprArray );

                    Mage::getModel( 'core/config_data' )
                            ->load( $CRON_STRING_PATH, 'path' )
                            ->setValue( $cronExprString )
                            ->setPath( $CRON_STRING_PATH )
                            ->save();
                    Mage::getModel( 'core/config_data' )
                            ->load( $CRON_MODEL_PATH, 'path' )
                            ->setValue( ( string ) Mage::getConfig()->getNode( $CRON_MODEL_PATH ) )
                            ->setPath( $CRON_MODEL_PATH )
                            ->save();
                    // clear config cache to refresh settings
                    Mage::getConfig()->cleanCache();
                } else {
                    // no post data meaning scheduled tasks is disabled, as browsers do not send a value for unchecked checkboxes
                    Mage::getModel( 'core/config_data' )
                            ->load( $ENABLED_PATH, 'path' )
                            ->setValue( 0 )
                            ->setPath( $ENABLED_PATH )
                            ->save();
                    // clear config cache to refresh settings
                    Mage::getConfig()->cleanCache();
                }
                Mage::getSingleton( 'adminhtml/session' )->addSuccess( Mage::helper( 'adminhtml' )->__( 'Setting successfully saved!' ) );
                $this->_redirect( '*/*/' );
            } catch (Exception $e) {
                Mage::getSingleton( 'adminhtml/session' )->addError( $e->getMessage() );
                $this->_redirect( '*/*/' );
            }
        }
        $this->_redirect( '*/*/' );
    }

}



