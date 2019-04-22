<?php

if (!defined('GNUSOCIAL')) {
    exit(1);
}

class Nodeinfo_2_0Action extends ApiAction
{
    private $plugins;

    protected function handle()
    {
        parent::handle();

        $this->plugins = $this->getActivePluginList();

        $this->showNodeInfo();
    }

    function getActivePluginList()
    {
        $pluginversions = array();
        $plugins = array();

        Event::handle('PluginVersion', array(&$pluginversions));

        foreach($pluginversions as $plugin) {
            $plugins[strtolower($plugin['name'])] = 1;
        }

        return $plugins;
    }

    /*
     * Technically, the NodeInfo spec defines 'active' as 'signed in at least once',
     * but GNU social doesn't keep track of when users last logged in, so let's return
     * the number of users that 'posted at least once', I guess.
     */
    function getActiveUsers($days)
    {
        $notices = new Notice();
        $notices->joinAdd(array('profile_id', 'user:id'));
        $notices->whereAdd('notice.created >= NOW() - INTERVAL ' . $days . ' DAY');

        $activeUsersCount = $notices->count('distinct profile_id');

        return $activeUsersCount;
    }

    function getRegistrationsStatus()
    {
        $areRegistrationsClosed = (common_config('site', 'closed')) ? true : false;
        $isSiteInviteOnly = (common_config('site', 'inviteonly')) ? true : false;

        return !($areRegistrationsClosed || $isSiteInviteOnly);
    }

    function getUserCount()
    {
        $users = new User();
        $userCount = $users->count();

        return $userCount;
    }

    function getPostCount()
    {
        $notices = new Notice();
        $notices->is_local = Notice::LOCAL_PUBLIC;
        $notices->whereAdd('reply_to IS NULL');
        $noticeCount = $notices->count();

        return $noticeCount;
    }

    function getCommentCount()
    {
        $notices = new Notice();
        $notices->is_local = Notice::LOCAL_PUBLIC;
        $notices->whereAdd('reply_to IS NOT NULL');
        $commentCount = $notices->count();

        return $commentCount;
    }

    function getProtocols()
    {
        $oStatusEnabled = array_key_exists('ostatus', $this->plugins);
        $xmppEnabled = (array_key_exists('xmpp', $this->plugins) && common_config('xmpp', 'enabled')) ? true : false;
        $protocols = array();

        if (Event::handle('StartNodeInfoProtocols', array(&$protocols))) {
            // Until the OStatus and XMPP plugins handle this themselves,
            // try to figure out if they're enabled ourselves.

            if ($oStatusEnabled) {
                $protocols[] = 'ostatus';
            }

            if ($xmppEnabled) {
                $protocols[] = 'xmpp';
            }
        }
        Event::handle('EndNodeInfoProtocols', array(&$protocols));

        return $protocols;
    }

    function getInboundServices()
    {
        // FIXME: Are those always on?
        $inboundServices = array('atom1.0', 'rss2.0');

        if (array_key_exists('twitterbridge', $this->plugins) && $config['twitterimport']['enabled']) {
            $inboundServices[] = 'twitter';
        }

        if (array_key_exists('ostatus', $this->plugins)) {
            $inboundServices[] = 'gnusocial';
        }

        return $inboundServices;
    }

    function getOutboundServices()
    {
        $xmppEnabled = (array_key_exists('xmpp', $this->plugins) && common_config('xmpp', 'enabled')) ? true : false;

        // FIXME: Are those always on?
        $outboundServices = array('atom1.0', 'rss2.0');

        if (array_key_exists('twitterbridge', $this->plugins)) {
            $outboundServices[] = 'twitter';
        }

        if (array_key_exists('ostatus', $this->plugins)) {
            $outboundServices[] = 'gnusocial';
        }

        if ($xmppEnabled) {
            $outboundServices[] = 'xmpp';
        }

        return $outboundServices;
    }

    function showNodeInfo()
    {
        $openRegistrations = $this->getRegistrationsStatus();
        $userCount = $this->getUserCount();
        $postCount = $this->getPostCount();
        $commentCount = $this->getCommentCount();

        $usersActiveHalfyear = $this->getActiveUsers(180);
        $usersActiveMonth = $this->getActiveUsers(30);

        $protocols = $this->getProtocols();
        $inboundServices = $this->getInboundServices();
        $outboundServices = $this->getOutboundServices();

        $json = json_encode([
            'version' => '2.0',

            'software' => [
                'name' => 'gnusocial',
                'version' => GNUSOCIAL_VERSION
            ],

            'protocols' => $protocols,

            // TODO: Have plugins register services
            'services' => [
                'inbound' => $inboundServices,
                'outbound' => $outboundServices
            ],

            'openRegistrations' => $openRegistrations,

            'usage' => [
                'users' => [
                    'total' => $userCount,
                    'activeHalfyear' => $usersActiveHalfyear,
                    'activeMonth' => $usersActiveMonth
                ],
                'localPosts' => $postCount,
                'localComments' => $commentCount
            ],

            'metadata' => new stdClass()
        ]);

        $this->initDocument('json');
        print $json;
        $this->endDocument('json');
    }
}

