<?php
namespace AllPlayers\Store;

use stdClass;

class MockClient extends Client
{
    /**
     * @nicetohave
     * Line items in the cart services users cart.
     *
     * @return array
     *   Array of cart line item objects.
     */
    public function usersCartIndex()
    {
        $return = array();

        $line_item = new stdClass();
        $line_item->quantity = 2;
        $line_item->title = 'Foo';
        $line_item->unit_price = '$100.00';
        $line_item->line_price = '$200.00';
        $line_item->group_name = 'Test Group';
        $return[] = $line_item;

        $line_item = new stdClass();
        $line_item->quantity = 1;
        $line_item->title = 'Bar';
        $line_item->unit_price = '$50.00';
        $line_item->line_price = '$50.00';
        $line_item->group_name = 'Another Group';
        $return[] = $line_item;

        return $return;
    }

    /**
     * @musthave
     * Add items to the services users cart.
     *
     * @param string $product_uuid
     *
     * @return boolean
     *   TRUE if succesfully added.
     */
    public function usersCartAdd($product_uuid)
    {
        return true;
    }

    public function groupStoreIndex()
    {
        $return = array();

        $group = new stdClass();
        $group->uuid = '1234';
        $group->store_status = '0';
        $group->title = 'Test Title';
        $group->uri = "$this->base_url/something/$group->uuid";
        $return[] = $group;

        $group = new stdClass();
        $group->uuid = '2222';
        $group->store_status = '0';
        $group->title = 'Inactive Store';
        $group->uri = "$this->base_url/something/$group->uuid";
        $return[] = $group;

        $group = new stdClass();
        $group->uuid = '3334';
        $group->store_status = '1';
        $group->title = 'Another Test Store';
        $group->uri = "$this->base_url/something/$group->uuid";
        $return[] = $group;

        return $return;
    }

    /**
     * @musthave
     *
     * @param string $uuid
     *   Group UUID string.
     *
     * @todo Get information about a group store if it exists.
     */
    public function groupStoreGet($uuid)
    {
        $group = new stdClass();
        $group->store_status = '1';
        $group->title = 'The test group';
        $group->uri = "$this->base_url/something/$uuid";
        $group->uuid = $uuid;

        return $group;
    }

    /**
     * @musthave
     *
     * @param string $uuid
     *   Group UUID string.
     *
     * @todo Initialize and enable a group store now.
     * @todo This will require different privileges? Or should we just expect
     * the current user to have that?
     */
    public function groupStoreActivate($uuid)
    {
        $group_store = new stdClass();
        $group_store->uuid = $uuid;
        $group_store->title = 'The test group';
        $group_store->uri = $this->base_url.'/store/1';

        return $group_store;
    }

    /**
     * @musthave
     *
     * @param string $group_uuid
     * @param string $type
     *
     * @return array
     *   Array of product objects.
     *
     * @todo List group products, optionally by type.
     */
    public function groupStoreProductsIndex($group_uuid, $type = null)
    {
        return array(
            $this->productGet(0),
            $this->productGet(1),
        );
    }

    /**
     * @nicetohave
     * @param string $uuid
     */
    public function productGet($uuid)
    {
        $product = new stdClass();
        if ($uuid == 1) {
            $product->title = 'Registration for Player';
            $product->uuid = '11cf8960-ef54-11e0-be50-0800200c9a66';
            $product->link = $this->base_url . '/products/321';
            $product->type = 'registration_fee';
            $product->roleName = 'Player';
            $product->rid = '321';
            $product->price = '100.00';
            $product->installmentsAllowed = '1';
            $product->installmetsInitialPrice = '50.00';
            $product->installments = array(
                'date' => '5/23/2012', 'price' => '40.00',
                'date' => '6/20/2012', 'price' => '30.00',
            );
        } else {
            $product->installmentsAllowed = '0';
            $product->link = $this->base_url . '/products/123';
            $product->price_raw = '2000';
            $product->rid = '123';
            $product->roleName = 'Coach';
            $product->title = 'Registration for Coach';
            $product->type = 'registration_fee';
            $product->uri = "$this->base_url/something/$uuid";
            $product->uuid = '275e7cf0-ef54-11e0-be50-0800200c9a66';
        }

        return $product;
    }

}
