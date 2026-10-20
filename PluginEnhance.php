<?php

use Illuminate\Database\Capsule\Manager as Db;

require_once 'modules/admin/models/ServerPlugin.php';
require 'api/vendor/autoload.php';

class PluginEnhance extends ServerPlugin
{
    public $features = [
        'packageName' => true,
        'testConnection' => true,
        'showNameservers' => true,
        'directlink' => true,
        'upgrades' => true,
        'publicPanels' => [
            'usage' => 'Resource usage'
        ]
    ];

    public $icons = [
        'usage' => 'fa fa-chart-bar'
    ];

    public function getVariables()
    {
        $variables = [
            'Name' => [
                'type' => 'hidden',
                'description' => 'Used by CE to show plugin',
                'value' => 'Enhance'
            ],
            'Description' => [
                'type' => 'hidden',
                'description' => 'Description viewable by admin in server settings',
                'value' => lang('Enhance Plugin')
            ],
            'Access Token' => [
                'type' => 'text',
                'description' => lang('Enter your Access token'),
                'value' => ''
            ],
            'orgId' => [
                'type' => 'text',
                'description' => lang('Enter your Enhance Organization ID'),
                'value' => '',
            ],
            "OrgId Custom Field" => [
                "type" => "text",
                "description" => lang("Enter the name of the package custom field that will hold the User Org Id."),
                "value" => ""
            ],
            "SubId Custom Field" => [
                "type" => "text",
                "description" => lang("Enter the name of the package custom field that will hold the User website subscription Id."),
                "value" => ""
            ],
            'Actions' => [
                'type' => 'hidden',
                'description' => 'Current actions that are active for this plugin per server',
                'value' => 'Create,Delete,Suspend,UnSuspend'
            ],
            'Registered Actions For Customer' => [
                'type' => 'hidden',
                'description' => 'Current actions that are active for this plugin per server for customers',
                'value' => ''
            ],
            'package_vars' => [
                'type' => 'hidden',
                'description' => 'Whether package settings are set',
                'value' => '0',
            ],
            'package_vars_values' => [
                'type'  => 'hidden',
                'description' => lang('Package Settings'),
                'value' => []
            ]
        ];

        return $variables;
    }

    public function validateCredentials($args)
    {
    }

    public function doDelete($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $orgId = $userPackage->getCustomField($args['server']['variables']['plugin_enhance_OrgId_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $existing_sub = $userPackage->getCustomField($args['server']['variables']['plugin_enhance_SubId_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $api = $this->getApiClient($args);
        $api['subscriptionsClient']->deleteSubscription($orgId, $existing_sub, "false");
        $userPackage->setCustomField($args['server']['variables']['plugin_enhance_SubId_Custom_Field'], "", CUSTOM_FIELDS_FOR_PACKAGE);
        return $userPackage->getCustomField("Domain Name") . ' has been deleted.';
    }

    public function doCreate($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);

        $api = $this->getApiClient($args);

        $orgId = $userPackage->getCustomField($args['server']['variables']['plugin_enhance_OrgId_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        if (empty($orgId)) {
            $orgId = $this->getOrCreateCustomer($api, $args);
            $userPackage->setCustomField(
                $args['server']['variables']['plugin_enhance_OrgId_Custom_Field'],
                $orgId,
                CUSTOM_FIELDS_FOR_PACKAGE
            );
        }

        $subscriptionId = $userPackage->getCustomField($args['server']['variables']['plugin_enhance_SubId_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        if (empty($subscriptionId)) {
            $planId = $this->getPlanId(
                $api,
                $args['server']['variables']['plugin_enhance_orgId'],
                $args['package']['name_on_server']
            );

            $new_subscription = new \OpenAPI\Client\Model\NewSubscription();
            $new_subscription->setPlanId(intval($planId));
            $subscription = $api['subscriptionsClient']->createCustomerSubscription(
                $args['server']['variables']['plugin_enhance_orgId'],
                $orgId,
                $new_subscription
            );

            $subscriptionId = $subscription['id'];
            $userPackage->setCustomField(
                $args['server']['variables']['plugin_enhance_SubId_Custom_Field'],
                $subscriptionId,
                CUSTOM_FIELDS_FOR_PACKAGE
            );
        }

        $userPackage->setCustomField('User Name', $args['customer']['email']);

        $new_website = new \OpenAPI\Client\Model\NewWebsite();
        $new_website->setSubscriptionId(intval($subscriptionId));
        $new_website->setDomain($args['package']['domain_name']);

        try {
            $api['websitesClient']->createWebsite($orgId, $new_website);
        } catch (Exception $e) {
            throw new CE_Exception($e->getMessage());
        }

        return $userPackage->getCustomField("Domain Name") . ' has been created.';
    }

    public function doSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $orgId = $userPackage->getCustomField($args['server']['variables']['plugin_enhance_OrgId_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $existing_sub = $userPackage->getCustomField($args['server']['variables']['plugin_enhance_SubId_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        ;

        $api = $this->getApiClient($args);
        $update_subscription = new \OpenAPI\Client\Model\UpdateSubscription();
        $update_subscription->setIsSuspended(true);

        $api['subscriptionsClient']->updateSubscription($orgId, $existing_sub, $update_subscription);
        return $userPackage->getCustomField("Domain Name") . ' has been suspended.';
    }

    public function doUnSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $orgId = $userPackage->getCustomField($args['server']['variables']['plugin_enhance_OrgId_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
        $existing_sub = $userPackage->getCustomField($args['server']['variables']['plugin_enhance_SubId_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);

        $api = $this->getApiClient($args);
        $update_subscription = new \OpenAPI\Client\Model\UpdateSubscription();
        $update_subscription->setIsSuspended(false);

        $api['subscriptionsClient']->updateSubscription($orgId, $existing_sub, $update_subscription);

        return $userPackage->getCustomField("Domain Name") . ' has been unsuspended.';
    }

    public function getApiClient($args)
    {
        $config = OpenAPI\Client\Configuration::getDefaultConfiguration()
            ->setAccessToken($args['server']['variables']['plugin_enhance_Access_Token'])
            ->setHost("https://" . $args['server']['variables']['ServerHostName'] . "/api");

        $guzzleConfig = [
            \GuzzleHttp\RequestOptions::VERIFY => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath()
        ];

        $customersClient = new OpenAPI\Client\Api\CustomersApi(
            new GuzzleHttp\Client($guzzleConfig),
            $config
        );
        $subscriptionsClient = new OpenAPI\Client\Api\SubscriptionsApi(
            new GuzzleHttp\Client($guzzleConfig),
            $config
        );
        $loginsClient = new OpenAPI\Client\Api\LoginsApi(
            new GuzzleHttp\Client($guzzleConfig),
            $config
        );
        $membersClient = new OpenAPI\Client\Api\MembersApi(
            new GuzzleHttp\Client($guzzleConfig),
            $config
        );
        $orgsClient = new OpenAPI\Client\Api\OrgsApi(
            new GuzzleHttp\Client($guzzleConfig),
            $config
        );

        $websitesClient = new OpenAPI\Client\Api\WebsitesApi(
            new GuzzleHttp\Client($guzzleConfig),
            $config
        );
        $serversClient = new OpenAPI\Client\Api\ServersApi(
            new GuzzleHttp\Client($guzzleConfig),
            $config
        );

        return array(
            "customersClient" => $customersClient,
            "subscriptionsClient" => $subscriptionsClient,
            "loginsClient" => $loginsClient,
            "membersClient" => $membersClient,
            "orgsClient" => $orgsClient,
            "websitesClient" => $websitesClient,
            'serversClient' => $serversClient
        );
    }

    private function getOrCreateCustomer($api, $args)
    {
        $packageIds = [];

        $pluginGateway = new PluginGateway($this->user);
        $userPackageGateway = new UserPackageGateway($this->user);

        $packages = $userPackageGateway->getUserHostingPackagesIterator(PACKAGE_TYPE_HOSTING, $args['customer']['id']);
        while ($userPackage = $packages->fetch()) {
            if ($userPackageGateway->hasPlugin($userPackage, $pluginName)) {
                if ($pluginName === 'enhance') {
                    $packageIds[] = $userPackage->id;
                }
            }
        }

        $customFieldId = Db::table('customField')->where('name', $args['server']['variables']['plugin_enhance_OrgId_Custom_Field'])->select('id')->first()->id;
        $orgId = Db::table("object_customField")->where("customFieldId", $customFieldId)->whereIn("objectid", $packageIds)->select('value')->first()->value;
        if ($orgId != "") {
            return $orgId;
        } else {
            return $this->createCustomer($api, $args);
        }
    }

    private function createCustomer($api, $args)
    {
        $new_customer = new \OpenAPI\Client\Model\NewCustomer();
        $name = "Customer from CE";

        $user = new User($args['customer']['id']);

        if ($user->getOrganization() && $user->getOrganization() != "") {
            $name = $user->getOrganization();
        } else {
            $name = $user->getFirstName() . ' ' . $user->getLastname();
        }

        $new_customer->setName($name);
        $org = $api['customersClient']->createCustomer(
            $args['server']['variables']['plugin_enhance_orgId'],
            $new_customer
        );

        $new_login = new \OpenAPI\Client\Model\LoginInfo();

        $new_login->setEmail($args['customer']['email']);
        $new_login->setName($name);
        $new_login->setPassword($args['package']['password']);

        try {
            $login = $api['loginsClient']->createLogin($org['id'], $new_login);
        } catch (Exception $e) {
            $this->deleteOrphanedOrg($api, $org['id']);
            throw new CE_Exception($e->getMessage());
        }

        $new_member = new \OpenAPI\Client\Model\NewMember();
        $new_member->setLoginId($login['id']);
        $new_member->setRoles([\OpenAPI\Client\Model\Role::OWNER]);

        try {
            $member = $api['membersClient']->createMember($org['id'], $new_member);
        } catch (Exception $e) {
            $this->deleteOrphanedOrg($api, $org['id']);
            throw new CE_Exception($e->getMessage());
        }

        return $org['id'];
    }

    private function deleteOrphanedOrg($api, $orgId)
    {
        try {
            $api['orgsClient']->deleteOrg($orgId, "true");
        } catch (Exception $e) {
            CE_Lib::log(4, 'Failed to clean up orphaned Enhance org ' . $orgId . ': ' . $e->getMessage());
        }
    }

    public function testConnection($args)
    {
        CE_Lib::log(4, 'Testing connection to Enhance server');
        $api = $this->getApiClient($args);
        try {
            $response = $api['websitesClient']->getWebsites($args['server']['variables']['plugin_enhance_orgId']);
        } catch (Exception $e) {
            throw new CE_Exception($e->getMessage());
        }
    }

    public function getAvailableActions($userPackage)
    {
        $args = $this->buildParams($userPackage);
        $subId =  $userPackage->getCustomField(
            $args['server']['variables']['plugin_enhance_SubId_Custom_Field'],
            CUSTOM_FIELDS_FOR_PACKAGE
        );
        $orgId = $userPackage->getCustomField(
            $args['server']['variables']['plugin_enhance_OrgId_Custom_Field'],
            CUSTOM_FIELDS_FOR_PACKAGE
        );
        $actions = [];

        if (empty($subId)) {
            $actions[] = 'Create';
            return $actions;
        }

        $api = $this->getApiClient($args);

        try {
            $result = $api['subscriptionsClient']->getSubscription($orgId, $subId);

            $websites = $api['websitesClient']->getWebsites($orgId, null, null, null, null, null, null, null, intval($subId))->getItems();
            if (empty($websites)) {
                $actions[] = 'Create';
                return $actions;
            }

            if (empty($result['suspended_by'])) {
                $actions[] = 'Suspend';
            } else {
                $actions[] = 'UnSuspend';
            }
            $actions[] = 'Delete';
        } catch (Exception $e) {
            $actions[] = 'Create';
        }

        return $actions;
    }

    public function getDirectLink($userPackage, $getRealLink = true, $fromAdmin = false, $isReseller = false)
    {
        $args = $this->buildParams($userPackage);
        $api = $this->getApiClient($args);
        $linkText = $this->user->lang('Login to Panel');

        if ($fromAdmin) {
            $cmd = 'panellogin';
            return [
                'cmd' => $cmd,
                'label' => 'Login to Enhance'
            ];
        } elseif ($getRealLink) {
            $orgId = $userPackage->getCustomField(
                $args['server']['variables']['plugin_enhance_OrgId_Custom_Field'],
                CUSTOM_FIELDS_FOR_PACKAGE
            );

            $link = $this->getOwnerLoginLink($api, $orgId);

            return array(
                'fa' => 'fa fa-user fa-fw',
                'link' => $link,
                'text' => $linkText,
                'form' => ''
            );
        } else {
            $link = 'index.php?fuse=clients&controller=products&action=openpackagedirectlink&packageId=' . $userPackage->getId() . '&sessionHash=' . CE_Lib::getSessionHash();

            return [
                'fa' => 'fa fa-user fa-fw',
                'link' => $link,
                'text' => $linkText,
                'form' => ''
            ];
        }
    }

    /**
     * One-time login link for the owner of an Enhance organization.
     *
     * @param array $api The clients from getApiClient().
     * @param string $orgId The Enhance organization id.
     * @return string
     * @throws Exception When the organization has no owner, or Enhance refuses the request.
     */
    private function getOwnerLoginLink($api, $orgId)
    {
        $members = $api['membersClient']->getMembers($orgId)->getItems();

        $owners = array_filter($members, function ($member) {
            $roles = $member->getRoles();
            return is_numeric(array_search("Owner", $roles));
        });

        // array_filter keeps the original keys, so the owner is not necessarily at index 0.
        $owner = reset($owners);

        if (!$owner) {
            throw new Exception("Unable to locate organization owner for direct login");
        }

        $link = $api['membersClient']->getOrgMemberLogin($orgId, $owner->getId());
        return trim($link, '"');
    }

    /**
     * The admin "Login to Enhance" command (getDirectLink's 'panellogin'), called with the command's args array.
     *
     * Other ClientExec routes can call a server plugin's public methods with different arguments, so a call without
     * the admin command's arguments is refused and never returns a panel login link.
     *
     * @param array $args The admin command's arguments, with userPackageId.
     * @return string A one-time login link, or '' for a call that did not come from the admin command.
     */
    public function dopanellogin($args)
    {
        if (!is_array($args) || empty($args['userPackageId'])) {
            CE_Lib::log(4, 'Enhance: dopanellogin refused a call without admin command arguments');
            return '';
        }
        $userPackage = new UserPackage($args['userPackageId']);
        $response = $this->getDirectLink($userPackage);
        return $response['link'];
    }

    /**
     * Client area panel declared in $features['publicPanels']: each resource of the package's Enhance subscription,
     * with its usage and its plan limit, a warning when a resource is at 90% of its limit or more, and when the
     * figures were fetched.
     *
     * ClientExec calls this for the package id in the request (view=productsnapinview&publicPanel=1&key=usage). The
     * method checks that the package belongs to the signed-in client before it sends any request to Enhance.
     *
     * @param UserPackage $userPackage The package named in the request.
     * @param object $view The page's view, which ClientExec passes to every panel method. The figures are also set
     *                     on it as $view->enhanceUsage (getUsage()'s array), so a custom theme can draw its own panel.
     * @return string The panel's HTML, or a short notice when Enhance cannot be reached.
     * @throws CE_Exception When the package belongs to another client.
     */
    public function usage($userPackage, $view)
    {
        if ($userPackage->getCustomerId() != $this->user->getId()) {
            throw new CE_Exception($this->user->lang('You are trying to access a product that does not exist.'));
        }

        // Translations and labels are text, not markup.
        $e = function ($text) {
            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $usage = $this->getUsage($userPackage);
        if ($usage === null) {
            return '<p>' . $e($this->user->lang('Resource usage is not available right now. Please try again later.')) . '</p>';
        }
        $view->enhanceUsage = $usage;

        $html = '';
        if ($usage['notice'] !== null) {
            $html .= '<div class="alert alert-warning">' . $e($usage['notice']) . '</div>';
        }
        $html .= '<table class="table"><thead><tr><th>' . $e($this->user->lang('Resource')) . '</th><th>' . $e($this->user->lang('Used')) . '</th><th>' . $e($this->user->lang('Limit')) . '</th></tr></thead><tbody>';
        foreach ($usage['rows'] as $row) {
            $html .= '<tr><td>' . $e($row['label']) . '</td><td>' . $e($row['used']);
            if ($row['percent'] !== null) {
                $tone = $row['percent'] >= 100 ? ' bg-danger' : ($row['percent'] >= 90 ? ' bg-warning' : '');
                $html .= '<div class="progress mt-1" style="height: 6px;"><div class="progress-bar' . $tone . '" role="progressbar" style="width: ' . $row['percent'] . '%" aria-valuenow="' . $row['percent'] . '" aria-valuemin="0" aria-valuemax="100" aria-label="' . $e($row['label']) . '"></div></div>';
            }
            $html .= '</td><td>' . $e($row['limit']) . '</td></tr>';
        }
        return $html . '</tbody></table><p class="text-muted small">' . $e($usage['updated']) . '</p>';
    }

    /**
     * The package's Enhance subscription resources as display rows.
     *
     * The figures come from a single GET of the subscription and are kept in the client's session for five minutes,
     * so reloading a page does not query Enhance each time. Usage is never recalculated on request.
     *
     * @param UserPackage $userPackage A package that belongs to the signed-in client.
     * @return array|null Null when the package has no Enhance org or subscription yet, or when Enhance cannot be
     *                    reached (the error is logged). Otherwise:
     *                    - rows: name, label, size (whether the figures are in bytes), used and limit as display text,
     *                      and percent of the limit (0-100, rounded down) or null when the plan leaves it unlimited;
     *                    - notice: a sentence naming the resources at or near their limit, or null;
     *                    - fetched: when the figures were fetched from Enhance, as a Unix timestamp;
     *                    - updated: "Updated N minutes ago".
     */
    private function getUsage($userPackage)
    {
        $cacheKey = 'enhance_resources_' . $userPackage->getId();
        if (!isset($_SESSION[$cacheKey]) || $_SESSION[$cacheKey]['time'] < time() - 300) {
            $args = $this->buildParams($userPackage);
            $orgId = $userPackage->getCustomField($args['server']['variables']['plugin_enhance_OrgId_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
            $subId = $userPackage->getCustomField($args['server']['variables']['plugin_enhance_SubId_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
            if ($orgId == '' || $subId == '') {
                return null;
            }
            $api = $this->getApiClient($args);

            try {
                $subscription = $api['subscriptionsClient']->getSubscription($orgId, $subId);
            } catch (Exception $e) {
                CE_Lib::log(4, 'Enhance usage for package ' . $userPackage->getId() . ' failed: ' . $e->getMessage());
                return null;
            }

            $resources = [];
            foreach ($subscription->getResources() as $resource) {
                $resources[(string) $resource->getName()] = ['usage' => $resource->getUsage(), 'total' => $resource->getTotal()];
            }
            $_SESSION[$cacheKey] = ['time' => time(), 'resources' => $resources];
        }
        $resources = $_SESSION[$cacheKey]['resources'];

        // Resources without a label here are left out.
        $labels = [
            'diskspace' => 'Disk space',
            'transfer' => 'Bandwidth',
            'websites' => 'Websites',
            'stagingWebsites' => 'Staging websites',
            'addonDomains' => 'Addon domains',
            'subdomains' => 'Subdomains',
            'domainAliases' => 'Domain aliases',
            'mailboxes' => 'Mailboxes',
            'forwarders' => 'Email forwarders',
            'mysqlDbs' => 'Databases',
            'ftpUsers' => 'FTP accounts',
            'dnsRecords' => 'DNS records',
            'customers' => 'Customers',
            'pageViews' => 'Page views',
            'processes' => 'Processes'
        ];

        $rows = [];
        $full = [];
        $near = [];
        foreach ($resources as $name => $resource) {
            if (!isset($labels[$name])) {
                continue;
            }
            $usage = $resource['usage'];
            $total = $resource['total'];
            $size = in_array($name, ['diskspace', 'transfer']);
            $label = $this->user->lang($labels[$name]);

            // Enhance omits the total of a resource the plan leaves unlimited; a total of 0 means none is allowed.
            if ($total === null) {
                $percent = null;
            } elseif ($total > 0) {
                $percent = (int) min(100, floor($usage * 100 / $total));
            } else {
                $percent = $usage > 0 ? 100 : 0;
            }
            if ($percent === 100) {
                $full[] = $label;
            } elseif ($percent >= 90) {
                $near[] = $label;
            }

            $rows[] = [
                'name' => $name,
                'label' => $label,
                'size' => $size,
                'used' => $this->formatUsage($usage, $size),
                'limit' => $total === null ? $this->user->lang('Unlimited') : $this->formatUsage($total, $size),
                'percent' => $percent
            ];
        }

        $notice = [];
        if ($full) {
            $notice[] = $this->user->lang("You've reached the limit for %s.", $this->listLabels($full));
        }
        if ($near) {
            $notice[] = $this->user->lang("You're nearly at the limit for %s.", $this->listLabels($near));
        }

        $fetched = $_SESSION[$cacheKey]['time'];
        $minutes = (int) floor((time() - $fetched) / 60);
        if ($minutes < 1) {
            $updated = $this->user->lang('Updated just now');
        } elseif ($minutes == 1) {
            $updated = $this->user->lang('Updated 1 minute ago');
        } else {
            $updated = $this->user->lang('Updated %s minutes ago', $minutes);
        }

        return [
            'rows' => $rows,
            'notice' => $notice ? implode(' ', $notice) : null,
            'fetched' => $fetched,
            'updated' => $updated
        ];
    }

    /**
     * Joins resource labels for a sentence: "Disk space", "Disk space and Websites", "Disk space, Websites and Mailboxes".
     *
     * @param string[] $labels
     * @return string
     */
    private function listLabels($labels)
    {
        $last = array_pop($labels);
        return $labels ? implode(', ', $labels) . ' ' . $this->user->lang('and') . ' ' . $last : $last;
    }

    /**
     * Formats a resource figure for display. Sizes use decimal units (1 GB is 1000000000 bytes), as Enhance's plan
     * settings do.
     *
     * @param int $value The figure as Enhance reports it; sizes are in bytes.
     * @param bool $bytes Whether the figure is a size.
     * @return string
     */
    private function formatUsage($value, $bytes)
    {
        if (!$bytes) {
            return number_format($value);
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;
        while ($value >= 1000 && $unit < count($units) - 1) {
            $value /= 1000;
            $unit++;
        }
        return round($value, 1) . ' ' . $units[$unit];
    }

    public function doUpdate($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $this->update($this->buildParams($userPackage, $args));
        return $userPackage->getCustomField("Domain Name") . ' has been updated.';
    }

    public function update($args)
    {
        $api = $this->getApiClient($args);
        $userPackage = new UserPackage($args['package']['id']);

        $subId =  $userPackage->getCustomField(
            $args['server']['variables']['plugin_enhance_SubId_Custom_Field'],
            CUSTOM_FIELDS_FOR_PACKAGE
        );
        $orgId = $userPackage->getCustomField(
            $args['server']['variables']['plugin_enhance_OrgId_Custom_Field'],
            CUSTOM_FIELDS_FOR_PACKAGE
        );

        foreach ($args['changes'] as $key => $value) {
            switch ($key) {
                case 'package':
                    $planId = $this->getPlanId(
                        $api,
                        $args['server']['variables']['plugin_enhance_orgId'],
                        $args['package']['name_on_server']
                    );

                    $api['subscriptionsClient']->updateSubscription(
                        $orgId,
                        $subId,
                        ['planId' => intval($planId)]
                    );

                    break;
            }
        }
    }

    private function getPlanId($api, $orgId, $planName)
    {
        $plans = $api['orgsClient']->getPlans($orgId)->getItems();

        foreach ($plans as $plan) {
            if ($planName == $plan->getName()) {
                return $plan->getId();
            }
        }

        throw new CE_Exception('Enhance plan "' . $planName . '" was not found. The package name on server must match an Enhance plan name exactly.');
    }
}