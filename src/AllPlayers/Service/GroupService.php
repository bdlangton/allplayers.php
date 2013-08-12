<?php
namespace AllPlayers\Service;

use AllPlayers\Objects\Group;

/**
 * Interact with AllPlayers via Client using Group objects.
 *
 * @todo - Add _RUD methods.
 */
class GroupService extends ClientService
{
    /**
     * Create a group represented by Group.
     *
     * @param Group $group
     *   The group to create, with no UUID provided.
     * @return Group
     *   The created group with UUID populated.
     */
    public function create(Group $group)
    {
        $location = array('zip' => $group->zip);
        $category = array($group->category);

        $params = array(
            'title' => $group->title,
            'description' => $group->description,
            'location' => $location,
            'category' => $category,
            'web_address' => $group->purl,
        );

        $response = $this->client->post('groups', array_filter($params));

        $group->uuid = $response->uuid;
        return $group;
    }
}
