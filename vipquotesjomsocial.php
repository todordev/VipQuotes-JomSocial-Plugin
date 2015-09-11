<?php
/**
 * @package         VipQuotes
 * @subpackage      Plugins
 * @author          Todor Iliev
 * @copyright       Copyright (C) 2014 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license         http://www.gnu.org/licenses/gpl-3.0.en.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die;

jimport('Prism.init');
jimport('VipQuotes.init');

/**
 * This plugin sends notification and adds activity record to users' activity stream.
 *
 * @package        VipQuotes
 * @subpackage     Plugins
 */
class plgContentVipQuotesJomSocial extends JPlugin
{
    /**
     * A JRegistry object holding the parameters for the plugin
     *
     * @var    Joomla\Registry\Registry
     * @since  1.5
     */
    public $params = null;
    
    protected $autoloadLanguage = true;

    /**
     * This method is executed when the administrator change the state of a quote.
     *
     * @param string  $context
     * @param array   $ids
     * @param integer $state
     *
     * @return null|boolean
     */
    public function onContentChangeState($context, $ids, $state)
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        if (!$app->isAdmin()) {
            return null;
        }

        if (strcmp("com_vipquotes.quote", $context) != 0) {
            return null;
        }

        if ($state != 1) {
            return null;
        }

        // Check for enabled option for adding activity stream.

        try {
            foreach ($ids as $id) {
                $item = new VipQuotes\Quote\Quote(JFactory::getDbo());
                $item->load($id);

                $properties = $item->getProperties();
                $item       = Joomla\Utilities\ArrayHelper::toObject($properties);

                if ($this->params->get("add_activity", 0)) {
                    $this->addActivity($item);
                }

                if ($this->params->get("send_notification", 0)) {
                    $this->sendNotification($item);
                }

            }
        } catch (Exception $e) {
            $app->enqueueMessage(JText::_("PLG_CONTENT_VIPQUOTESJOMSOCIAL_ERROR_SOCIAL_INTEGRATION_PROBLEM"), "error");
        }

        return true;
    }

    /**
     * This method is executed when user store a quote.
     *
     * @param string  $context
     * @param object  $item
     * @param boolean $isNew
     * @param boolean $isChangedState
     *
     * @return null|boolean
     */
    public function onContentAfterSave($context, $item, $isNew, $isChangedState = false)
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        if (strcmp("com_vipquotes.quote", $context) != 0) {
            return null;
        }

        try {

            // Check for enabled option for adding activity stream.
            if ($this->params->get("add_activity", 0)) {
                if (!empty($item->id) and $item->published and ($isNew or $isChangedState)) {
                    $this->addActivity($item);

                    if ($this->params->get("send_notification", 0) and $isChangedState) {
                        $this->sendNotification($item);
                    }

                }
            }

        } catch (Exception $e) {
            $app->enqueueMessage(JText::_("PLG_CONTENT_VIPQUOTESJOMSOCIAL_ERROR_SOCIAL_INTEGRATION_PROBLEM"), "error");
        }

        return true;
    }

    protected function addActivity($item)
    {
        $content = JText::_("PLG_CONTENT_VIPQUOTESJOMSOCIAL_ACTIVITY_CONTENT");
        $content .= "<blockquote>" . strip_tags($item->quote) . "</blockquote>";

        // Create activity stream object.
        $activity = new Prism\Integration\Activity\JomSocial($item->user_id, $content);
        $activity
            ->setDb(JFactory::getDbo())
            ->setApp("com_vipquotes.post")
            ->store();
    }

    protected function sendNotification($item)
    {
        $content = JText::_("PLG_CONTENT_VIPQUOTESJOMSOCIAL_NOTIFICATION_PUBLISHED");

        // Create notification.
        $notification = new Prism\Integration\Notification\JomSocial($item->user_id, $content);
        $notification
            ->setDb(JFactory::getDbo())
            ->setCmdType("notif_system_messaging")
            ->setActorId($this->params->get("notification_actor", 0))
            ->send();
    }
}
