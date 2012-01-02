<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Controller.php 4886 2011-06-06 11:39:12Z peterb $
 *
 * @category Piwik_Plugins
 * @package Piwik_PrivacyManager
 */

/**
 *
 * @package Piwik_PrivacyManager
 */
class Piwik_PrivacyManager_Controller extends Piwik_Controller_Admin
{

    const ANONYMIZE_IP_PLUGIN_NAME = "AnonymizeIP";
    const OPTION_LAST_DELETE_PIWIK_LOGS = "lastDelete_piwik_logs";

    public function index()
    {
        Piwik::checkUserIsSuperUser();
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            switch (Piwik_Common::getRequestVar('form')) {
                case("formMaskLength"):
                    $this->handlePluginState(Piwik_Common::getRequestVar("anonymizeIPEnable", 0));
                    $maskLength = Zend_Registry::get('config')->Tracker;
                    $maskLength->ip_address_mask_length = Piwik_Common::getRequestVar("maskLength", 1);
                    Zend_Registry::get('config')->Tracker = $maskLength->toArray();
                    break;
                case("formDeleteSettings"):
                    $deleteLogs = Zend_Registry::get('config')->Deletelogs;
                    $deleteLogs->delete_logs_enable = Piwik_Common::getRequestVar("deleteEnable", 0);
                    $deleteLogs->delete_logs_schedule_lowest_interval = Piwik_Common::getRequestVar("deleteLowestInterval", 7);
                    $deleteLogs->delete_logs_older_than = ((int)Piwik_Common::getRequestVar("deleteOlderThan", 180) < 7) ?
                            7 : Piwik_Common::getRequestVar("deleteOlderThan", 180);
                    $deleteLogs->delete_max_rows_per_run = Piwik_Common::getRequestVar("deleteMaxRows", 100);

                    Zend_Registry::get('config')->Deletelogs = $deleteLogs->toArray();
                    break;
                default: //do nothing
                    break;
            }
        }
        return $this->redirectToIndex('PrivacyManager', 'privacySettings', null, null, null, array('updated' => 1));
    }

    public function privacySettings()
    {
        Piwik::checkUserHasSomeAdminAccess();
        $view = Piwik_View::factory('privacySettings');

        if (Piwik::isUserIsSuperUser()) {
            $deleteLogs = array();

            $view->deleteLogs = $this->getDeleteLogsInfo();
            $view->anonymizeIP = $this->getAnonymizeIPInfo();
        }
        $view->language = Piwik_LanguagesManager::getLanguageCodeForCurrentUser();

        if (!Zend_Registry::get('config')->isFileWritable()) {
            $view->configFileNotWritable = true;
        }

        $this->setBasicVariablesView($view);
        $view->menu = Piwik_GetAdminMenu();

        echo $view->render();
    }

    protected function getAnonymizeIPInfo()
    {
        Piwik::checkUserIsSuperUser();
        $anonymizeIP = array();

        Piwik_PluginsManager::getInstance()->loadPlugin(self::ANONYMIZE_IP_PLUGIN_NAME);

        $anonymizeIP["name"] = self::ANONYMIZE_IP_PLUGIN_NAME;
        $anonymizeIP["enabled"] = Piwik_PluginsManager::getInstance()->isPluginActivated(self::ANONYMIZE_IP_PLUGIN_NAME);
        $anonymizeIP["maskLength"] = Zend_Registry::get('config')->Tracker->ip_address_mask_length;
        $anonymizeIP["info"] = Piwik_PluginsManager::getInstance()->getLoadedPlugin(self::ANONYMIZE_IP_PLUGIN_NAME)->getInformation();

        return $anonymizeIP;
    }

    protected function getDeleteLogsInfo()
    {
        Piwik::checkUserIsSuperUser();
        $deleteLogsInfos = array();
        $taskScheduler = new Piwik_TaskScheduler();
        $deleteLogsInfos["config"] = Zend_Registry::get('config')->Deletelogs->toArray();
        $privacyManager = new Piwik_PrivacyManager();
        $deleteLogsInfos["deleteTables"] = implode(", ", $privacyManager->getDeleteTableLogTables());

        $scheduleTimetable = $taskScheduler->getScheduledTimeForTask("Piwik_PrivacyManager", "deleteLogTables");

        $optionTable = Piwik_GetOption(self::OPTION_LAST_DELETE_PIWIK_LOGS);

        //If task was already rescheduled, read time from taskTimetable. Else, calculate next possible runtime.
        if(!empty($scheduleTimetable) && ($scheduleTimetable - time() > 0)) {
            $nextPossibleSchedule = (int)$scheduleTimetable;
        } else {
            $date = Piwik_Date::factory("today");
            $nextPossibleSchedule = $date->addDay(1)->getTimestamp();
        }

        //deletion schedule did not run before
        if (empty($optionTable)) {
            $deleteLogsInfos["lastRun"] = false;

            //next run ASAP (with next schedule run)
            $date = Piwik_Date::factory("today");
            $deleteLogsInfos["nextScheduleTime"] = $nextPossibleSchedule;
        } else {
            $deleteLogsInfos["lastRun"] = $optionTable;
            $deleteLogsInfos["lastRunPretty"] = Piwik_Date::factory((int)$optionTable)->getLocalized('%day% %shortMonth% %longYear%');

            //Calculate next run based on last run + interval
            $nextScheduleRun = (int)($deleteLogsInfos["lastRun"] + $deleteLogsInfos["config"]["delete_logs_schedule_lowest_interval"] * 24 * 60 * 60);

            //is the calculated next run in the past? (e.g. plugin was disabled in the meantime or something) -> run ASAP
            if (($nextScheduleRun - time()) <= 0) {
                $deleteLogsInfos["nextScheduleTime"] = $nextPossibleSchedule;
            } else {
                $deleteLogsInfos["nextScheduleTime"] = $nextScheduleRun;
            }
        }

        $deleteLogsInfos["nextRunPretty"] = Piwik::getPrettyTimeFromSeconds($deleteLogsInfos["nextScheduleTime"] - time());

        return $deleteLogsInfos;
    }

    protected function handlePluginState($state = 0)
    {
        $pluginController = new Piwik_CorePluginsAdmin_Controller();

        if ($state == 1 && !Piwik_PluginsManager::getInstance()->isPluginActivated(self::ANONYMIZE_IP_PLUGIN_NAME)) {
            $pluginController->activate($redirectAfter = false);
        } elseif ($state == 0 && Piwik_PluginsManager::getInstance()->isPluginActivated(self::ANONYMIZE_IP_PLUGIN_NAME)) {
            $pluginController->deactivate($redirectAfter = false);
        } else {
            //nothing to do
        }
    }

}
