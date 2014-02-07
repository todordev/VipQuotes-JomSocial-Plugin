<?php
/**
 * @package		 VipQuotes
 * @subpackage	 Plugins
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2014 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');
jimport('vipquotes.init');

/**
 * This plugin sends notification and adds activity record to users' activity stream.
 *
 * @package		VipQuotes
 * @subpackage	Plugins
 */
class plgContentVipQuotesJomSocial extends JPlugin {
    
    protected $autoloadLanguage = true;
    
    /**
     * This method is executed when the administrator change the state of a quote.
     * 
     * @param string            $context
     * @param array             $ids
     * @param integer           $state
     * 
     * @return boolean
     */
    public function onContentChangeState($context, $ids, $state) {
        
        $app = JFactory::getApplication();
        /** @var $app JSite **/

        if(!$app->isAdmin()) {
            return;
        }

        if(strcmp("com_vipquotes.quote", $context) != 0){
            return;
        }
        
        if($state != 1){
            return;
        }
        
        // Check for enabled option for adding activity stream.
        
        jimport("vipquotes.quote");
        
        try {
            foreach($ids as $id) {
                $item = new VipQuotesQuote(JFactory::getDbo());
                $item->load($id);
            
                $properties = $item->getProperties();
                $item       = JArrayHelper::toObject($properties);
                
                if($this->params->get("add_activity", 0)) {
                    $this->addActivity($item);
                }
                
                if($this->params->get("send_notification", 0)) {
                    $this->sendNotification($item);
                }
                
            }
        } catch (Exception $e){
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
     * @return void|boolean
     */
    public function onContentAfterSave($context, $item, $isNew, $isChangedState = false) {
    
        $app = JFactory::getApplication();
        /** @var $app JSite **/
    
        if(strcmp("com_vipquotes.quote", $context) != 0){
            return;
        }
    
        try {
            
            // Check for enabled option for adding activity stream.
            if($this->params->get("add_activity", 0)) {
                if(!empty($item->id) AND $item->published AND ($isNew OR $isChangedState)) {
                    $this->addActivity($item);
                }
            }
            
        } catch (Exception $e){
            $app->enqueueMessage(JText::_("PLG_CONTENT_VIPQUOTESJOMSOCIAL_ERROR_SOCIAL_INTEGRATION_PROBLEM"), "error");
        }
        
        return true;
    
    }
    
    protected function addActivity($item) {
        
        $content  = JText::_("PLG_CONTENT_VIPQUOTESJOMSOCIAL_ACTIVITY_CONTENT");
        $content .= "<blockquote>".strip_tags($item->quote)."</blockquote>";
        
        // Create activity stream object.
        jimport("itprism.integrate.activity.jomsocial");
        $activity = new ITPrismIntegrateActivityJomSocial($item->user_id, $content);
        $activity->setType("com_vipquotes.quote.post");
        $activity->store();
            
    }
    
    protected function sendNotification($item) {
        
        $note    = JText::_("PLG_CONTENT_VIPQUOTESJOMSOCIAL_NOTIFICATION_PUBLISHED");
        
        // Create notification.
        jimport("itprism.integrate.notification.jomsocial");
        $activity = new ITPrismIntegrateNotificationJomSocial($item->user_id, $note);
        $activity->setType("notif_system_messaging");
        $activity->setActor($this->params->get("notification_actor", 0));
        $activity->send();
            
    }
}