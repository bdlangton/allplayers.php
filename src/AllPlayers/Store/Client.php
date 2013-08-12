<?php
namespace AllPlayers\Store;

use DateTime;
use DateTimeZone;
use InvalidArgumentException;

use AllPlayers\Component\HttpClient;

use Guzzle\Plugin\Cookie\CookiePlugin;
use Guzzle\Plugin\Log\LogPlugin;

class Client extends HttpClient
{
    /**
     * Proper format for dates.
     *
     * @var string
     */
    const DATE_FORMAT = 'Y-m-d';

    /**
     * Proper format for dates with time.
     *
     * @var string
     */
    const DATETIME_FORMAT = 'Y-m-d\TH:i:00';

    /**
     * Endpoint for the REST API.
     *
     * @var string
     *
     * @todo This isn't configurable upstream.
     */
    const ENDPOINT = '/api/v1/rest';

    /**
     * Default AllPlayers.com Store URL.
     *
     * @var string
     */
    public $base_url = null;

    /**
     * @param string $base_url
     *   e.g. "https://store.mercury.dev.allplayers.com".
     * @param Logger $logger
     *   (Optional)
     */
    public function __construct($base_url, LogPlugin $log_plugin = null, $cookie_plugin = null)
    {
        if (empty($base_url)) {
            throw new InvalidArgumentException('Invalid argument 1: base_url must be a base URL to the Store.');
        }
        $this->base_url = $base_url;
        parent::__construct($base_url . self::ENDPOINT, $log_plugin, $cookie_plugin);
    }

    /**
     * Returns the registration SKU.
     *
     * @param string $group_name
     *   The name of the group.
     * @param integer $role_id
     *   The role ID for the registration.
     *
     * @return string
     *   The SKU for the registration product.
     */
    public static function getRegistrationSKU($group_name, $role_id)
    {
        return strtolower(
            substr(
                preg_replace(
                    '/[^A-Za-z0-9\_]/',
                    '',
                    str_replace(' ', '_', $group_name)
                ),
                0,
                10
            )
        ) . "-registration_fee-$role_id";
    }

    /**
     * Returns the group registration product title.
     *
     * @param string $group_name
     *   The name of the group.
     * @param string $product_name
     *   The name of the product.
     * @param string $role_name
     *   The role name.
     *
     * @return string
     *   The title of the registration product.
     */
    public static function getRegistrationProductTitle($group_name, $product_name, $role_name)
    {
        return t(
            '!role !product for !group',
            array(
                '!role' => $role_name,
                '!product' => $product_name,
                '!group' => $group_name,
            )
        );
    }

    /**
     * @musthave
     * Link to users cart.
     */
    public function usersCartUrl()
    {
        return $this->base_url . '/cart';
    }

    /**
     * @musthave
     * Link to users orders.
     */
    public function usersOrdersUrl()
    {
        return $this->base_url . '/orders';
    }

    /**
     * @musthave
     * Link to users bills.
     */
    public function usersBillsUrl()
    {
        return $this->base_url . '/bills';
    }

    /**
     * @nicetohave
     * Line items in the cart services users cart.
     *
     * @param string $user_uuid
     *   User UUID string.
     *
     * @return array
     *   Array of cart line item objects.
     */
    public function usersCartIndex($user_uuid)
    {
        return $this->index("users/$user_uuid/cart");
    }

    /**
     * @musthave
     * Add items to the services users cart.
     *
     * @param string $user_uuid
     *   User UUID string.
     * @param string $product_uuid
     *   Product UUID string.
     * @param string $for_user_uuid
     *   Optional user UUID string representing the user that the product is
     *   acually being purchased for.
     * @param boolean $installment_plan
     *   Whether or not an installment plan should be used to purchase the
     *   product.
     * @param string $role_uuid
     *   UUID of a role to associate this purchase with for registration.
     * @param string $sold_by_uuid
     *   UUID of the group selling the product.
     * @param boolean $force_invoice
     *   Whether or not to force the product to be invoiced, regardless of the
     *   product's type and settings.
     * @param string $creator_uuid
     *   UUID of the user responsible for creating the new line item. Defaults
     *   to the authenticated user.
     *
     * @return stdClass
     *   The order that the product was added to.
     */
    public function usersCartAdd(
        $user_uuid,
        $product_uuid,
        $for_user_uuid = null,
        $installment_plan = false,
        $role_uuid = null,
        $sold_by_uuid = null,
        $force_invoice = false,
        $creator_uuid = null
    ) {
        return $this->post(
            "users/$user_uuid/add_to_cart",
            array(
                'product_uuid' => $product_uuid,
                'for_user_uuid' => $for_user_uuid,
                'installment_plan' => $installment_plan,
                'role_uuid' => $role_uuid,
                'sold_by_uuid' => $sold_by_uuid,
                'force_invoice' => $force_invoice,
                'creator_uuid' => $creator_uuid,
            ),
            $this->headers
        );
    }

    /**
     * Transfers any information from a temporary user to a new account.
     *
     * @param string $old_user_uuid
     *   The temporary user uuid.
     * @param string $new_user_uuid
     *   The new user's uuid.
     */
    public function userReplace($old_user_uuid, $new_user_uuid) {
        // @todo This has to be implemented on store.
//        $this->post(
//            "users/$old_user_uuid/replace_user",
//            array(
//                'new_user_uuid' => $new_user_uuid,
//            )
//        );
    }

    /**
     * Sync roles for a user.
     *
     * @param string $user_uuid
     *   User UUID string.
     * @param string $og_role
     *   Name of role to sync, such as guardian or friend.
     *
     * @return boolean
     *   TRUE if succesfully added.
     */
    public function usersRelationshipsSync($user_uuid, $og_role)
    {
        return $this->post(
            'users/' . $user_uuid . '/sync_relationships',
            array('og_role' => $og_role),
            $this->headers
        );
    }

    /**
     * Merges two users.
     *
     * Any entities owned by or referencing the user to be merged will be
     * updated to reference the base user. This resource is only available to
     * privileged users.
     *
     * @param string $base_user_uuid
     *   UUID of the user who is the base of the merge. This is the user that will
     *   remain.
     * @param string $merge_user_uuid
     *    UUID of the user to be merged into the base user. This user will be
     *   deleted.
     *
     * @return string
     *   UUID of the base user.
     */
    public function usersMerge($base_user_uuid, $merge_user_uuid)
    {
        return $this->post(
            "users/$base_user_uuid/merge/$merge_user_uuid",
            array(),
            $this->headers
        )->uuid;
    }

    /**
     * Return the group stores.
     *
     * @param string $user_uuid
     *   Filter the results based on the membership of this user.
     * @param boolean $is_admin
     *   Filter the results futher based on if user_uuid is an admin of those
     *   groups.
     * @param boolean $accepts_payment
     *   Filter the results futher based on if the user is an admin of a group
     *   that accepts their own payments.
     *
     * @return array
     */
    public function groupStoreIndex($user_uuid = '', $is_admin = false, $accepts_payment = false)
    {
        return $this->get(
            'group_stores',
            array(
                'user_uuid' => $user_uuid,
                'is_admin' => $is_admin ? 1 : 0,
                'accepts_payment' => $accepts_payment ? 1 : 0,
            )
        );
    }

    /**
     * @musthave
     * Link to group store.
     *
     * @param string $uuid
     */
    public function groupStoreUrl($uuid)
    {
        return "$this->base_url/group_store/uuid/$uuid";
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
        return $this->get("group_stores/$uuid");
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
        return $this->post('group_stores', array('uuid' => $uuid));
    }

    /**
     * Update a group store to match group information.
     *
     * @param string $uuid
     *   Group UUID string.
     */
    public function groupStoreUpdate($uuid, $data)
    {
        return $this->put("group_stores/$uuid", $data);
    }

    /**
     * Synchronize group store users with users on www
     *
     * @param string $uuid
     * @param boolean $admins_only
     * @param string $og_role
     */
    public function groupStoreSyncUsers($uuid, $admins_only = true, $og_role = null)
    {
        return $this->post(
            "group_stores/$uuid/sync_users",
            array(
                'admins_only' => $admins_only,
                'og_role' => $og_role,
            )
        );
    }

    /**
     * @musthave
     *
     * @param string $group_uuid
     *   UUID of the group to retrieve the products for.
     * @param string $type
     *   Optional type of products to retrieve.
     * @param boolean $available_for_sale
     *   Whether or not to return all products available for sale by the group.
     * @param integer|string $page
     *   (Optional) Numeric page number or '*' to fetch all pages.
     *   Default to 0. If using '*', it is recommended to set a high $pagesize
     *   to reduce the number of requests needed to retrieve the entire list.
     * @param integer $pagesize
     *   (Optional) Limit the number of results returned per page. If not set,
     *   then we default to 20.
     *   NOTE: This does not limit the overall return set when using the '*'
     *   page parameter.
     * @param boolean $show_disabled
     *   (Optional) Include disabled products in the result.
     *
     * @return array
     *   Array of product objects.
     *
     * @todo List group products, optionally by type.
     */
    public function groupStoreProductsIndex(
        $group_uuid,
        $type = null,
        $available_for_sale = false,
        $page = 0,
        $pagesize = 20,
        $show_disabled = false
    ) {
        $params = ($type) ? array('type' => $type) : array();
        $params['show_disabled'] = $show_disabled;

        // If the user wants all products available for sale then we need to
        // append that to the path.
        $path = "group_stores/$group_uuid/products";
        if ($available_for_sale) {
            $path .= '/available_for_sale';
        }

        return $this->index($path, $params, null, $page, $pagesize);
    }

    /**
     * Retrieve a single line item.
     *
     * @param string $uuid
     *   UUID of the line item to retrieve.
     *
     * @return stdClass
     *   Line item object.
     */
    public function lineItemsGet($uuid)
    {
        return $this->get("line_items/$uuid");
    }

    /**
     * Update a single line item.
     *
     * @param string $uuid
     *   UUID of the line item to update.
     * @param string $seller_uuid
     *   UUID of the group to set as the seller for the line item.
     * @param string $user_uuid
     *   UUID of the user that the line item is being purchased for.
     *
     * @return stdClass
     *   Updated line item object.
     */
    public function lineItemPut($uuid, $seller_uuid = null, $user_uuid = null)
    {
        return $this->put(
            "line_items/$uuid",
            array(
                'seller_uuid' => $seller_uuid,
                'user_uuid' => $user_uuid,
            )
        );
    }

    /**
     * Line Items Index.
     *
     * @param string $originating_order_uuid
     * @param string $product_uuid
     * @param string $originating_product_uuid
     * @param string $line_item_type
     * @param string $user_uuid
     * @param string $seller_uuid
     * @param string $fields
     * @param integer $pagesize
     * @param integer $page
     */
    public function lineItemsIndex(
        $originating_order_uuid = null,
        $product_uuid = null,
        $originating_product_uuid = null,
        $line_item_type = null,
        $user_uuid = null,
        $seller_uuid = null,
        $fields = null,
        $pagesize = 0,
        $page = 0
    ) {
        $params = array(
            'originating_order_uuid' => $originating_order_uuid,
            'originating_product_uuid' => $originating_product_uuid,
            'line_item_type' => $line_item_type,
            'product_uuid' => $product_uuid,
            'user_uuid' => $user_uuid,
            'seller_uuid' => $seller_uuid,
        );

        return $this->index('line_items', array_filter($params), $fields, $page, $pagesize);
    }

    /**
     * Retrieve an order.
     *
     * @param string $uuid
     *   The uuid of the order to get.
     */
    public function orderGet($order_uuid)
    {
        return $this->get("orders/$uuid");
    }

    /**
     * Create an order.
     *
     * @param string $user_uuid
     *    UUID of the user to create the order for.
     * @param string $group_uuid
     *   UUID of the group to create the order under. If the group is not the
     *   payee then the payee group will be used instead.
     * @param array $line_items
     *   Array of line items to be added to the new order. Each line item may
     *   include the following indexes:
     *   - product_uuid: UUID of the product to be referenced by the line item.
     *   - quantity: Quantity of the product for the line item. Defaults to 1.
     *   - for_user_uuid: The user that the product is being purchased for.
     *     Defaults to the same value as $user_uuid.
     *   - installment_plan: Whether or not to use an installment plan, if
     *     available, for the product. Defaults to FALSE.
     *   - role_uuid: UUID of a role that the product is being purchased for if
     *     purchased as part of registration.
     *   - sold_by_uuid: UUID of the group that is selling the product. Defaults
     *     to the same value as $group_uuid.
     *   - creator_uuid: UUID of the user who is creating this line item.
     *     Defaults to the logged in user.
     * @param string $order_status
     *   Initial status of the new order.
     * @param DateTime $created
     *   Order creation time. If omitted, the current date and time will be
     *   used.
     * @param DateTime $due_date
     *   Due date of the order if it is an invoice. Defaults to the current day.
     * @param array $billing_address
     *   Billing address to be set on the order. May include the following
     *   indexes:
     *   - street_1: First street address.
     *   - street_2: Second street address.
     *   - city: City of the address.
     *   - state: State of the address.
     *   - zip: Postal code of the address.
     *   - country: ISO 3166-1 three digit country code of the address.
     *   - first_name: First name of the person to be billed.
     *   - last_name: Last name of the person to be billed.
     * @param array $shipping_address
     *   Shipping address to be set on the order. Format should match the
     *   billing address. Defaults to the billing address if it has been set.
     * @param boolean $initial_payment_only
     *   If using an installment plan, only create an order for the initial
     *   payment. This will prevent installment payments from being created.
     *
     * @return stdClass
     *   Created object from api.
     */
    public function orderCreate(
        $user_uuid,
        $group_uuid,
        array $line_items,
        $order_status = 'cart',
        DateTime $created = null,
        DateTime $due_date = null,
        array $billing_address = array(),
        array $shipping_address = array(),
        $initial_payment_only = false
    ) {
        $params = array(
          'user_uuid' => $user_uuid,
          'group_uuid' => $group_uuid,
          'order_status' => $order_status,
          'line_items' => $line_items,
          'created' => (!empty($created)
              ? $created->setTimezone(new DateTimeZone('UTC'))->format(self::DATE_FORMAT)
              : null),
          'due_date' => (!empty($due_date)
              ? $due_date->format(self::DATE_FORMAT)
              : null),
          'billing_address' => $billing_address,
          'shipping_address' => $shipping_address,
          'initial_payment_only' => $initial_payment_only,
        );

        return $this->post('orders', array_filter($params));
    }

    /**
     * Add Payment to an order.
     *
     * @param string $order_uuid
     *   UUID of the order the payment is getting applied to.
     * @param string $payment_type
     *   What type of payment is it (in_person, ad_hoc, etc).
     * @param string $payment_amount
     *   The amount of the payment.
     * @param array $payment_details
     *   Additional details for payment_type.
     * @param DateTime $created
     *   Created on date and time of the payment. If omitted, the current date and
     *   time will be used.
     *
     * @return stdClass
     *   Object with the following properties:
     *   - transaction_id: ID of the payment transaction that was created.
     *   - instructions (optional): Any additional instructions are required for the
     *     user to complete payment (e.g. pay in person).
     */
    public function orderAddPayment(
        $order_uuid,
        $payment_type,
        $payment_amount,
        $payment_details = array(),
        DateTime $created = null
    ) {
        $params = array(
            'payment_type' => $payment_type,
            'payment_amount' => $payment_amount,
            'payment_details' => $payment_details,
            'created' => (!empty($created)
                ? $created->setTimezone(new DateTimeZone('UTC'))->format(self::DATETIME_FORMAT)
                : null),
        );

        return $this->post("orders/$order_uuid/add_payment", array_filter($params));
    }

    /**
     * @param string $order_uuid
     *   UUID of the order.
     * @param integer $series_id
     *   Which installment to create an invoice for. Use numbers 0 through
     *   number of installments - 1.
     * @param DateTime $created
     *   When the invoice was created.
     *
     * @return stdClass
     *   Object from api.
     */
    public function orderAddInstallmentInvoice($order_uuid, $series_id, DateTime $created)
    {
        $params = array(
            'series_id' => $series_id,
            'created' => (!empty($created)
                ? $created->setTimezone(new DateTimeZone('UTC'))->format(self::DATETIME_FORMAT)
                : null),
        );

        return $this->post("orders/$order_uuid/add_installment_invoice", $params, $this->headers);
    }

    /**
     * @nicetohave
     * @param string $uuid
     */
    public function productGet($uuid)
    {
        return $this->get("products/$uuid");
    }

    /**
     * Create a product in a group.
     *
     * @param string $type
     *   The type of product to be created.
     * @param string $group_uuid
     *   UUID of the group that the product belongs to.
     * @param integer $role_id
     *   Numeric id of the role that the product is being created for.
     * @param string $role_name
     *   Name of the role that the product is being created for.
     * @param boolean $installments_enabled
     *   Whether or not installments should be enabled for this product.
     * @param float $initial_payment
     *   Price of the initial payment if purchased with installments.
     * @param array $installments
     *   Array of installment payments. Each payment should have a "due_date"
     *   and "amount". The due date should be an object of type DateTime.
     * @param float $total
     *   Full price of the product if purchased without installments.
     * @param string $sku
     *   SKU for the new product, only required for "product" products.
     * @param string $title
     *   Title for the new product, only required for "product" products.
     *
     * @return stdClass
     *   The new product as returend by the API.
     *
     * @todo Accept a product object rather than all of these arguments.
     */
    public function productCreate(
        $type,
        $group_uuid,
        $role_id = null,
        $role_name = null,
        $installments_enabled = 0,
        $initial_payment = 0,
        $installments = array(),
        $total = 0,
        $sku = null,
        $title = null
    ) {
        // Iterate over all installments, setting their due dates to the
        // appropriate format.
        if (!empty($installments)) {
            foreach ($installments as $delta => $installment) {
                if (!($installment['due_date'] instanceof DateTime)) {
                    throw new InvalidArgumentException(
                        'Invalid argument: Installment due date must be an object of type DateTime.'
                    );
                }

                $installments[$delta]['due_date'] = $installment['due_date']->format(self::DATE_FORMAT);
            }
        }

        $params = array(
            'type' => $type,
            'group_uuid' => $group_uuid,
            'role_id' => $role_id,
            'role_name' => $role_name,
            'installments_enabled' => $installments_enabled,
            'initial_payment' => $initial_payment,
            'installments' => $installments,
            'total' => $total,
            'sku' => $sku,
            'title' => $title,
        );

        return $this->post('products', array_filter($params));
    }

    /**
     * @musthave
     * Link to product base path.
     *
     * @param string $uuid
     */
    public function productUrl($uuid)
    {
        return "$this->base_url/product/uuid/$uuid";
    }

    /**
     * Login via user endpoint. (Overriding)
     *
     * @param string $user
     *   Username.
     * @param string $pass
     *   Password.
     */
    public function userLogin($user, $pass)
    {
        // Changing login path to 'user/login' (was 'users/login').
        // 'user/' path is from core services. 'users/' path is custom resource.
        return $this->post('user/login', array('username' => $user, 'password' => $pass));
    }

    /**
     * Generate the embed HTML for a group store donation form.
     *
     * @param string $uuid
     *   UUID of the group with a group store.
     *
     * @return string
     *   HTML embed snip. Requires JS on the client.
     */
    public function embedDonateHtml($uuid)
    {
        return "<script src='{$this->base_url}/groups/{$uuid}/donation-embed/js'></script>";
    }

    /**
     * Set the group payment methods.
     *
     * @param string $group_uuid
     * @param string $method
     * @param array $method_info
     *
     * @return array
     *   Array of payment methods.
     */
    public function groupPaymentMethodSet($group_uuid, $method, $method_info = array())
    {
        return $this->post(
            "group_stores/$group_uuid/payment_method",
            array(
                'method' => $method,
                'method_info' => $method_info,
            )
        );
    }

    /**
     * Get the group payment methods.
     *
     * @param string $group_uuid
     * @param string $method
     *
     * @return array
     *   Array of payment methods.
     */
    public function groupPaymentMethodGet($group_uuid, $method = null)
    {
        if (is_null($method)) {
            return $this->get(
                "group_stores/$group_uuid/payment_methods",
                array(),
                $this->headers
            );
        } else {
            return $this->get(
                "group_stores/$group_uuid/payment_methods",
                array('method' => $method)
            );
        }
    }

    /**
     * Get the group payee.
     *
     * @param string $group_uuid
     *
     * @return stdClass
     *   Object with the following properties:
     *   - uuid: UUID of the group's payee. NULL if no payee has been set.
     */
    public function groupPayeeGet($group_uuid)
    {
        return $this->get("group_stores/$group_uuid/payee");
    }

    /**
     * Set the group payee.
     *
     * @param string $group_uuid
     * @param string $payee_uuid
     *   If not set, then use own payment configuration.
     *
     * @return stdClass
     *   Object with the following properties:
     *   - uuid: UUID of the payee that was set on the group.
     */
    public function groupPayeeSet($group_uuid, $payee_uuid = null)
    {
        if (is_null($payee_uuid)) {
            return $this->post("group_stores/$group_uuid/payee");
        } else {
            return $this->post(
                "group_stores/$group_uuid/payee",
                array('payee_uuid' => $payee_uuid)
            );
        }
    }
}
