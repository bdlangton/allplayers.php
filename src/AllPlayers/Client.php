<?php
namespace AllPlayers;

use AllPlayers\Service\UserService;
use AllPlayers\Service\GroupService;

use AllPlayers\Component\HttpClient;
use Guzzle\Plugin\Log\LogPlugin;

use ErrorException;

/**
 * Methods for interacting with the main AllPlayers application API.
 */
class Client extends HttpClient
{
    /**
     * {@inheritdoc}
     */
    public function __construct($base_url, LogPlugin $log_plugin = null)
    {
        parent::__construct("$base_url/api/v1/rest", $log_plugin);
    }

    /**
     * Get UserService for this instance.
     *
     * @return UserService
     */
    public function userService()
    {
        return new UserService($this);
    }

    /**
     * Get GroupService for this instance.
     *
     * @return GroupService
     */
    public function groupService()
    {
        return new GroupService($this);
    }

    /**
     * Fetch a user by uuid.
     *
     * @param string $uuid
     *
     * @return stdClass
     *   User object.
     */
    public function userGetUser($uuid)
    {
        return $this->get("users/$uuid");
    }

    /**
     * Get a list of users based on fields passed in.
     *
     * @param array $field
     *   Parameters to pass as a query string while searching for users.
     *
     * @return array
     *   This is an array of user objects.
     */
    public function usersIndex($fields = array())
    {
        return $this->get('users', $fields);
    }

    /**
     * Update a user.
     *
     * @param string $uuid
     * @param string $firstname
     * @param string $lastname
     * @param string $email
     * @param string $gender
     * @param string $birthday
     * @param string $password
     * @param string $last_modified
     * @param array $address
     *
     * @return stdClass
     *   Uuser object.
     */
    public function userUpdateUser(
        $uuid,
        $firstname,
        $lastname,
        $email,
        $gender,
        $birthday,
        $password = null,
        $last_modified = null,
        $address = null
    ) {
        $params = array(
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'gender' => $gender,
            'birthday' => $birthday,
            'password' => $password,
            'last_modified' => $last_modified,
            'address' => $address
        );

        return $this->put("users/$uuid", array_filter($params));
    }

    /**
     * Create a user.
     *
     * @param string $firstname
     * @param string $lastname
     * @param string $email
     * @param string $gender
     * @param string $birthday
     * @param string $password
     * @param array $address
     *
     * @return stdClass
     *   User object.
     *
     * @todo Create seperate Exception for captcha to simplify this code.
     */
    public function userCreateUser($firstname, $lastname, $email, $gender, $birthday, $password = null, $address = null)
    {
        $userData = array(
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'email'     => $email,
            'gender'    => $gender,
            'birthday'  => $birthday,
            'password'  => $password,
            'address'   => $address
        );
        try {
            $ret = $this->post('users', array_filter($userData));
        } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $messageJson = $e->getResponse()->getBody();
            $messageParts = json_decode($messageJson);
            if (!empty($messageParts->form_errors)) {
                // If there are form errors besides captcha.
                throw $e;
            }

            // Retry if captcha error.
            if (isset($messageParts->captcha_error)) {
                $answer = $this->captchaSolve($messageParts->captcha_error->captcha_problem);
                $headers = array(
                    'X-ALLPLAYERS-CAPTCHA-TOKEN'        => $messageParts->captcha_error->captcha_token,
                    'X-ALLPLAYERS-CAPTCHA-SOLUTION' => $answer,
                );
                $ret = $this->post('users', array_filter($userData), $headers);
            }
        }

        return $ret;
    }

    /**
     * Solve a captcha.
     *
     * @param string $problem
     *   Math captchas look like "7 + 4 = ".
     *
     * @return string
     *   The correct captcha answer.
     */
    public function captchaSolve($problem)
    {
        $parts = explode('+', $problem);
        $math1 = trim($parts[0]);
        $math2parts = explode('=', $parts[1]);
        $math2 = trim($math2parts[0]);
        $answer = $math1 + $math2;

        return (string) $answer;
    }

    /**
     * List users groups based on parameters.
     *
     * @param string $uuid
     *   User uuid.
     * @param string $fields
     *   Comma separated string of fields to retrieve.
     * @param integer $page
     *   Which page to call.
     * @param integer $pagesize
     *   How many results to return per page.
     */
    public function userGetMyGroups($uuid, $fields = null, $page = 0, $pagesize = null)
    {
        // Compile path.
        $path = "users/$uuid/groups";

        return $this->index($path, $parameters = null, $fields, $page, $pagesize);
    }

    /**
     * List users groupmates based on parameters.
     *
     * @param string $uuid
     *   User uuid.
     * @param string $fields
     *   Comma separated string of fields to retrieve.
     * @param integer $page
     *   Which page to call.
     * @param integer $pagesize
     *   How many results to return per page.
     */
    public function userGetGroupmates($uuid, $fields = null, $page = 0, $pagesize = null)
    {
        // Compile path.
        $path = "users/$uuid/groupmates";

        return $this->index($path, $parameters = null, $fields, $page, $pagesize);
    }

    /**
     * List users friends based on parameters.
     *
     * @param string $uuid
     *   User uuid.
     * @param string $fields
     *   Comma separated string of fields to retrieve.
     * @param integer $page
     *   Which page to call.
     * @param integer $pagesize
     *   How many results to return per page.
     */
    public function userGetFriends($uuid, $fields = null, $page = 0, $pagesize = null)
    {
        return $this->userGetUsersByRelationship($uuid, 'friend', $parameters = null, $fields, $page, $pagesize);
    }

    /**
     * List users who have the designated relationship to the user.
     *
     * @param string $user_uuid
     *   UUID of user.
     * @param string $relationship
     *   Relationship to use for getting other users, such as 'guardian' or
     *   'friend'.
     * @param string $fields
     *   Comma separated string of fields to retrieve.
     * @param integer $page
     *   Which page to call.
     * @param integer $pagesize
     *   How many results to return per page.
     *
     * @return array
     *   Array of users who have the designated relationship to the user.
     */
    public function userGetUsersByRelationship($user_uuid, $relationship, $parameters = null, $fields = null, $page = 0, $pagesize = null)
    {
        switch ($relationship) {
            case 'guardian':
                $path = 'users/' . $user_uuid . '/guardians';
                break;
            case 'friend':
                $path = 'users/' . $user_uuid . '/friends';
                break;
        }
        if (!empty($path)) {
            return $this->index($path, $parameters, $fields, $page, $pagesize);
        }
    }

    /**
     * List user's friend requests based on parameters.
     *
     * @param string $uuid
     *   User uuid.
     */
    public function userGetFriendRequests($uuid)
    {
        // Compile path.
        $path = "users/$uuid/friendrequests";

        return $this->index($path);
    }

    /**
     * Request to be a friend with a user.
     *
     * @param string $uuid
     *   User uuid of requestee.
     */
    public function userRequestFriend($uuid)
    {
        // Compile path.
        $path = "users/$uuid/requestfriend";

        return $this->post($path);
    }

    /**
     * Approve a friendship request.
     *
     * @param string $uuid
     *   User uuid of requestee.
     * @param integer $request_id
     *   ID of friend request to approve.
     */
    public function userApproveFriend($uuid, $request_id)
    {
        // Compile path.
        $path = "users/$uuid/approvefriend/$request_id";

        return $this->post($path);
    }

    /**
     * List users events based on parameters.
     *
     * @param string $uuid
     *   User uuid.
     * @param string $fields
     *   Comma separated string of fields to retrieve.
     * @param integer $page
     *   Which page to call.
     * @param integer $pagesize
     *   How many results to return per page.
     */
    public function userGetEvents($uuid, $fields = null, $page = 0, $pagesize = null)
    {
        // Compile path.
        $path = "users/$uuid/events";

        return $this->index($path, $parameters = null, $fields, $page, $pagesize);
    }

    /**
     * List users upcoming events based on parameters.
     *
     * @param string $uuid
     *   User uuid.
     * @param string $fields
     *   Comma separated string of fields to retrieve.
     * @param integer $page
     *   Which page to call.
     * @param integer $pagesize
     *   How many results to return per page.
     */
    public function userGetEventsUpcoming($uuid, $fields = null, $page = 0, $pagesize = null)
    {
        // Compile path.
        $path = "users/$uuid/events/upcoming";

        return $this->index($path, $parameters = null, $fields, $page, $pagesize);
    }

    /**
     * Login via user endpoint.
     *
     * @param string $user
     *   Username.
     * @param string $pass
     *   Password.
     */
    public function userLogin($user, $pass)
    {
        $ret = $this->post('users/login', array('username' => $user, 'password' => $pass));

        return $ret;
    }

    /**
     * Logout via user endpoint.
     */
    public function userLogout($fast = true)
    {
        $ret = null;
        if (!$fast) {
            $ret = $this->post('users/logout');
        }

        // Clear cookies.
        $this->cookiePlugin->getCookieJar()->remove();

        return $ret;
    }


    /**
     * Retrieve a specific group.
     *
     * @param string $uuid
     *   Group uuid.
     * @param string $fields
     *   Comma separated string of fields to retrieve.
     */
    public function groupsGetGroup($uuid, $fields = null)
    {
        // Compile path.
        $path = "groups/$uuid";
        $query = array();
        if ($fields) {
            $query['fields'] = $fields;
        }

        return $this->get($path, $query);
    }

    /**
     * Create a group.
     *
     * @param string $title
     * @param string $description
     * @param array $location
     *   Contains group location information in an array.
     *   $location['zip'] is required at minimum.
     * @param array $category
     *   Contains group category in an array.
     *   $category[0] = 'sports'.
     * @param array $optional_config
     *   Contains optional groups configuration. Possible keys:
     *   - group_type - What type of group is this.
     *   - web_address - Groups web address. after www.allplayers.com/g/.
     *   - status - Active or inactive.
     *   - groupmates_enabled - FALSE or TRUE.
     *   - groups_above - Array of parent groups.
     *
     * @return stdClass
     *   User object.
     */
    public function groupsCreateGroup($title, $description, $location, $category, $optional_config)
    {
        $params = array(
            'title' => $title,
            'description' => $description,
            'location' => $location,
            'category' => $category,
            'group_type' => $optional_config['group_type'],
            'web_address' => $optional_config['web_address'],
            'status' => $optional_config['status'],
            'groupmates_enabled' => $optional_config['groupmates_enabled'],
            'groups_above' => $optional_config['groups_above'],
        );

        return $this->post('groups', array_filter($params));
    }

    /**
     * List groups based on parameters.
     *
     * @param string $search
     *   A search term to search for.
     * @param integer $zip
     *   A zip code to search around.
     * @param integer $search_distance
     *   How big the radius of the search from the zipcode should be.
     * @param string $search_units
     *   What units to use for search_distance.
     * @param string $fields
     *   Comma separated list of fields that need to come back.
     * @param integer $pagesize
     *   How many results to retrieve per page.
     * @param integer $page
     *   What page of the results to call.
     */
    public function groupsIndex(
        $search = null,
        $zip = null,
        $search_distance = 10,
        $search_units = 'mile',
        $fields = null,
        $pagesize = null,
        $page = 0
    ) {
        // Compile path.
        $path = 'groups';
        $parameters = array(
            'search' => $search,
            'distance' => array(
                'postal_code' => $zip,
                'search_distance' => $search_distance,
                'search_units' => $search_units,
            ),
            'feature' => 'All',
        );

        return $this->index($path, $parameters, $fields, $page, $pagesize);
    }

    /**
     * List a groups members based on parameters.
     *
     * @param string $uuid
     *   Group uuid.
     * @param string $fields
     *   Optional comma seperated list of fields to return.
     * @param integer $page
     *   Page of items to return, up to 20 per page.
     * @param integer $pagesize
     *   How many results should come back per page.
     */
    public function groupsGetMembers($uuid, $fields = null, $page = 0, $pagesize = null)
    {
        // Compile path.
        $path = "groups/$uuid/members";

        return $this->index($path, $parameters = null, $fields, $page, $pagesize);
    }

    /**
     * List a groups albums based on parameters.
     *
     * @param string $uuid
     *   Group uuid.
     * @param string $fields
     *   Optional comma seperated list of fields to return.
     * @param integer $page
     *   Page of items to return, up to 20 per page.
     * @param integer $pagesize
     *   How many results should come back per page.
     */
    public function groupsGetAlbums($uuid, $fields = null, $page = 0, $pagesize = null)
    {
        // Compile path.
        $path = "groups/$uuid/albums";

        return $this->index($path, $parameters = null, $fields, $page, $pagesize);
    }

    /**
     * List a groups photos based on parameters.
     *
     * @param integer $uuid
     *   Group uuid.
     * @param string $fields
     *   Optional comma seperated list of fields to return.
     * @param integer $page
     *   Page of items to return, up to 20 per page.
     * @param integer $pagesize
     *   How many results should come back per page.
     */
    public function groupsGetPhotos($uuid, $fields = null, $page = 0, $pagesize = null)
    {
        // Compile path.
        $path = "groups/$uuid/photos";

        return $this->index($path, $parameters = null, $fields, $page, $pagesize);
    }

    /**
     * List a groups events based on parameters.
     *
     * @param string $uuid
     *   Group uuid.
     * @param string $fields
     *   Optional comma seperated list of fields to return.
     * @param integer $page
     *   Page of items to return, up to 20 per page.
     * @param integer $pagesize
     *   How many results should come back per page.
     */
    public function groupsGetEvents($uuid, $fields = null, $page = 0, $pagesize = null)
    {
        // Compile path.
        $path = "groups/$uuid/events";

        return $this->index($path, $parameters = null, $fields, $page, $pagesize);
    }

    /**
     * List a groups upcoming based on parameters.
     *
     * @param string $uuid
     *   Group uuid.
     * @param string $fields
     *   Optional comma seperated list of fields to return.
     * @param integer $page
     *   Page of items to return, up to 20 per page.
     * @param integer $pagesize
     *   How many results should come back per page.
     */
    public function groupsGetEventsUpcoming($uuid, $fields = null, $page = 0, $pagesize = null)
    {
        // Compile path.
        $path = "groups/$uuid/events/upcoming";

        return $this->index($path, $parameters = null, $fields, $page, $pagesize);
    }

    /**
     * List roles belonging to a group (group_uuid), optionally targeting a
     * specific user (user_uuid).
     *
     * @param string $group_uuid
     *   UUID of group from which to get roles.
     * @param string $user_uuid
     *   UUID of user in group.
     */
    public function groupsGetRoles($group_uuid, $user_uuid = null)
    {
        $path = "groups/$group_uuid/roles";
        if (!empty($user_uuid)) {
            $path .= "/$user_uuid";
        }

        return $this->index($path);
    }

    /**
     * Join a user to a group.
     *
     * @param string $group_uuid
     * @param string $user_uuid
     *
     * @return stdClass
     *   User object.
     */
    public function groupsJoinUser($group_uuid, $user_uuid)
    {
        return $this->post("groups/$group_uuid/join/$user_uuid");
    }

    /**
     * Set a group's manager.
     *
     * @param string $group_uuid
     * @param string $user_uuid
     *
     * @return stdClass
     *   User object.
     */
    public function groupsSetManager($group_uuid, $user_uuid)
    {
        return $this->post("groups/$group_uuid/setmanager/$user_uuid");
    }

    /**
     * Notify a group about an order.
     *
     * @param string $group_uuid
     * @param array $order
     *   Order data including a $line_items array.
     *
     * @return boolean
     *   Whether the notification was received.
     */
    public function groupsNotifyOrder($group_uuid, $order)
    {
        return $this->post("groups/$group_uuid/ordernotify", $order);
    }

    /**
     * Create a notifier.
     *
     * @param string $title
     *   The title of the notifier node.
     * @param string $body
     *   The body of the notifier, which will be displayed to users.
     * @param array $optional_config
     *   Contains optional notifier configuration. Possible keys:
     *   - type - The type of notifier: error, warning, success, or info.
     *   - users - An array of usernames (not UID) that the notifier is directed
     *     to.
     *   - groups - An array of group NIDs that the notifier is directed to.
     *   - identifier - An identifier value to uniquely identify this notifier.
     *   - visibility - The visibility of the notifier: group members or anyone.
     *     Only applies to notifiers in group space.
     *   - global_notifier - Specifies if the notifier is global in either group
     *     or user space.
     *   - group_filter - Specifies if the notifier is directed to admins or
     *     non-admins. Only useful if the global notifier is set to group space.
     *
     * @return stdClass
     *   Notifier node.
     */
    public function notifiersCreateNotifier($title, $body, $optional_config)
    {
        $params = array(
            'title' => $title,
            'body' => $body,
            'type' => isset($optional_config['type']) ? $optional_config['type'] : 'warning',
            'users' => isset($optional_config['users']) ? $optional_config['users'] : array(),
            'groups' => isset($optional_config['groups']) ? $optional_config['groups'] : array(),
            'identifier' => isset($optional_config['identifier']) ? $optional_config['identifier'] : '',
            'visibility' => isset($optional_config['visibility'])
                ? $optional_config['visibility']
                : 'group members',
            'global_notifier' => isset($optional_config['global_notifier'])
                ? $optional_config['global_notifier']
                : null,
            'group_filter' => isset($optional_config['group_filter'])
                ? $optional_config['group_filter']
                : null,
        );

        return $this->post('notifier', array_filter($params));
    }

    /**
     * Returns a specific album's photos based on parameters.
     *
     * @param string $uuid
     *   Album uuid.
     * @param string $fields
     *   Optional comma seperated list of fields to return.
     * @param integer $page
     *   Page of items to return, up to 20 per page.
     * @param integer $pagesize
     *   How many results should come back per page.
     */
    public function albumsGetPhotos($uuid, $fields = null, $page = 0, $pagesize = null)
    {
        // Compile path.
        $path = "albums/$uuid/photos";

        return $this->index($path, $parameters = null, $fields, $page, $pagesize);
    }

    /**
     * Return a specific photo based on parameters.
     *
     * @param integer $id
     *   Photo id that should be retrieved.
     * @param string $fields
     *   Optional comma separated list of fields to return.
     */
    public function photosGetPhoto($pid, $fields = null)
    {
        // Compile path.
        $path = "photos/$pid";
        $query = array('fields' => $fields);

        return $this->get($path, $query);
    }

    /**
     * Create an event
     *
     * @param array $groups
     *   A numerically keyed array of group uuids.
     * @param string $title
     * @param string $description
     * @param array $date_time
     *   Contains event date information with all times in UTC.
     *   $date_time['start'] and $date_time['end'] required at minimum.
     * @param string $category
     *   Contains event category.
     * @param array $resources
     *   A numerically keyed array of resource uuids.
     * @param array $competitors
     *   An array of competitors groups UUIDs, labels and scores. Example:
     *   @code
     *     $competitors = array(
     *         'c964752e-eead-11e0-abff-080027706aa2' => array(
     *             'label' => 'Home Team',
     *             'score' => '35',
     *         ),
     *         'b0f67f02-6179-11e1-9932-b37b4e17875f' => array(
     *             'label' => 'Away Team'
     *             'score' => '30',
     *         ),
     *     );
     *   @endcode
     * @param string $published
     *   TRUE/FALSE whether event will be published.
     * @param string $external_id
     *   An external ID to associate to a remote database.
     *
     * @return stdClass
     *   User object.
     */
    public function eventsCreateEvent(
        $groups,
        $title,
        $description,
        $date_time,
        $category = null,
        $resources = null,
        $competitors = null,
        $published = true,
        $external_id = null
    ) {
        $params = array(
            'groups' => $groups,
            'title' => $title,
            'description' => $description,
            'date_time' => $date_time,
            'category' => $category,
            'resources' => $resources,
            'competitors' => $competitors,
            'published' => $published,
            'external_id' => $external_id,
        );

        return $this->post('events', array_filter($params));
    }

    /**
     * Update an event.
     *
     * @param string $event_uuid
     * @param array $groups
     *   A numerically keyed array of group uuids.
     * @param string $title
     * @param string $description
     * @param array $date_time
     *   Contains event date information with all times in UTC.
     *   $date_time['start'] and $date_time['end'] required at minimum.
     * @param string $category
     *   Contains event category.
     * @param array $resources
     *   A numerically keyed array of resource uuids.
     * @param array $competitors
     *   An array of competitors groups UUIDs, labels and scores. Example:
     *   @code
     *     $competitors = array(
     *         'c964752e-eead-11e0-abff-080027706aa2' => array(
     *             'label' => 'Home Team',
     *             'score' => '35',
     *         ),
     *         'b0f67f02-6179-11e1-9932-b37b4e17875f' => array(
     *             'label' => 'Away Team'
     *             'score' => '30',
     *         ),
     *     );
     *   @endcode
     * @param string $published
     *   TRUE/FALSE whether event will be published.
     * @param string $external_id
     *   An external ID to associate to a remote database.
     *
     * @return stdClass
     *   User object.
     */
    public function eventsUpdateEvent(
        $event_uuid,
        $groups = null,
        $title = null,
        $description = null,
        $date_time = null,
        $category = null,
        $resources = null,
        $competitors = null,
        $published = true,
        $external_id = null
    ) {
        $params = array(
            'groups' => $groups,
            'title' => $title,
            'description' => $description,
            'date_time' => $date_time,
            'category' => $category,
            'resources' => $resources,
            'competitors' => $competitors,
            'published' => $published,
            'external_id' => $external_id,
        );

        return $this->put("events/$event_uuid", array_filter($params));
    }

    /**
     * Return a specific event.
     *
     * @param integer $eid
     *   Event id.
     * @param string $fields
     *   Optional comma seperated list of fields to return.
     */
    public function eventsGetEvent($eid, $fields = null)
    {
        // Compile path.
        $path = "events/$eid";
        $query = array();
        if ($fields) {
            $query['fields'] = $fields;
        }

        return $this->get($path, $query);
    }

    /**
     * Return a specific resource based on parameters.
     *
     * @param integer $rid
     *   Resource id.
     * @param string $fields
     *   Optional comma seperated list of fields to return.
     */
    public function resourcesGetResource($uuid, $fields = null)
    {
        // Compile path.
        $path = "resources/$uuid";
        $query = array();
        if ($fields) {
            $query['fields'] = $fields;
        }

        return $this->get($path, $query);
    }

    /**
     * Create a resource.
     *
     * @param array $groups
     *   An array of group_uuids this resource belongs to.
     * @param string $title
     *   Resource title.
     * @param array $location
     *   Location array. $location['zip'] required at minimum.
     * @param array $availability
     *   Array of the resource's availabilities. If none passed, the resource is
     *   assumed to be always available.
     * @param string $external_id
     *   A 72 character string to associate to external data.
     *
     * @return stdClass
     *   Resource object.
     *
     * @todo Why is this taking a node?
     */
    public function resourceCreate($groups, $title, $location, $availability = null, $external_id = null)
    {
        $params = array(
            'groups' => $groups,
            'title' => $title,
            'location' => $location,
            'availability' => $availability,
            'external_id' => $external_id,
        );

        return $this->post('resources', array_filter($params));
    }

    /**
     * Update a resource.
     *
     * @param string $uuid
     *   the UUID of the resource being updated.
     * @param array $groups
     *   An array of group_uuids this resource belongs to.
     * @param string $title
     *   Resource title.
     * @param array $location
     *   Location array. $location['zip'] required at minimum.
     * @param array $availability
     *   Array of the resource's availabilities. If none passed, the resource is
     *   assumed to be always available.
     * @param string $external_id
     *   A 72 character string to associate to external data.
     *
     * @return stdClass
     *   Resource object.
     *
     * @todo Why is this taking a node?
     */
    public function resourceUpdate(
        $uuid,
        $groups = null,
        $title = null,
        $location = null,
        $availability = null,
        $external_id = null
    ) {
        $params = array(
            'groups' => $groups,
            'title' => $title,
            'location' => $location,
            'availability' => $availability,
            'external_id' => $external_id,
        );

        return $this->put("resources/$uuid", array_filter($params));
    }

    /**
     * Returns a single message or thread based on parameters.
     *
     * @param integer $mid
     *   Id of the message or thread to retrieve.
     * @param string $type
     *   Optional string specifying whether to retrieve thread or msg. If not
     *   passed, the API will default to type = "thread".
     * @param string $fields
     *   Optional comma seperated list of fields to return.
     *
     * @todo $type = null or thread?
     */
    public function messagesGetMessageOrThread($id, $type = null, $fields = null)
    {
        // Compile path.
        $path = "messages/$id";
        $query = array();
        if ($type) {
            $query['type'] = $type;
        }
        if ($fields) {
            $query['fields'] = $fields;
        }

        return $this->get($path, $query);
    }

    /**
     * List messages based on parameters.
     *
     * @param string $box
     *   Which box of messages to retrieve ('list' retrieves all, 'inbox' or
     *   'sent').
     * @param string $fields
     *   Optional comma seperated list of fields to return.
     * @param integer $pagesize
     *   How many messages to retrieve per page.
     * @param integer $page
     *   Page of items to return, up to 20 per page.
     *
     * @todo $type = null or thread?
     */
    public function messagesGetBox($box = null, $fields = null, $pagesize = null, $page = 0)
    {
        // Compile path.
        $path = 'messages';
        $parameters = array();
        if ($box) {
            $parameters['box'] = $box;
        }

        // Convert pages to offset because the public API has been changed to
        // accept offsets directly for messages.
        $offset = $page * $pagesize;

        return $this->index($path, $parameters, $fields, $offset, $pagesize);
    }

    /**
     * Creates a message or thread by parameters.
     *
     * @param integer $thread_id
     *   If this is a reply to a current thread, pass the thread id, but not
     *   recipients or subject.
     * @param string $recipients
     *   Comma separated string of recipient UIDs. Pass only if this is a new
     *   message.
     * @param string $subject
     *   Message subject. Pass only if this is new message.
     * @param string $body
     *   Message body. Always required.
     */
    public function messagesCreateMessageOrThread($thread_id = null, $recipients = null, $subject = null, $body = null)
    {
        // Compile path.
        $path = 'messages';
        $params = array('body' => $body);
        if ($thread_id) {
            $params['thread_id'] = $thread_id;
        }
        if ($recipients) {
            $params['recipients'] = $recipients;
        }
        if ($subject) {
            $params['subject'] = $subject;
        }

        return $this->post($path, $params);
    }

    /**
     * Updates the read status of message or thread.
     *
     * @param integer $id
     *   Id of the message or thread.
     * @param integer $status
     *   1 for new, 0 for read.
     * @param string $type
     *   Optional string specifying whether thread or msg. If not passed, the
     *   API will default to type = "thread".
     */
    public function messagesUpdateMessageOrThread($id, $status, $type = null)
    {
        // Compile path.
        $path = "messages/$id";
        $params = array('status' => $status,);
        if ($type) {
            $params['type'] = $type;
        }

        return $this->put($path, $params);
    }

    /**
     * Returns a single message or thread based on parameters.
     *
     * @param integer $id
     *   Id of the message or thread to delete.
     * @param string $type
     *   Optional string specifying whether to retrieve thread or msg. If not
     *   passed, the API will default to type = "thread".
     */
    public function messagesDeleteMessageOrThread($id, $type = null)
    {
        // Compile path.
        $path = "messages/$id";
        $query = array();
        if ($type) {
            $query['type'] = $type;
        }

        return $this->delete($path, $query);
    }

    /**
     * Fetch HTML information from webapp.
     *
     * @param string $section
     *   The section of HTML to retrieve (header or footer).
     *
     * @return string
     *   HTML code for the header.
     */
    public function themeGetHtml($section = 'header')
    {
        return $this->get("theme/$section");
    }
}
