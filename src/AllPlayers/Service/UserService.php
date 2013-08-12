<?php
namespace AllPlayers\Service;

use AllPlayers\Objects\User;
use AllPlayers\Exceptions\ObjectNotFoundException;
use DateTime;
use stdClass;

/**
 * Interact with AllPlayers via Client using User objects.
 *
 * @todo - Add _RUD methods.
 */
class UserService extends ClientService
{
    /**
     * Logs a user into the api
     *
     * @param User $user
     *   The user you want logged in.
     *
     * @throws \Guzzle\Http\Exception\BadResponseException
     */
    public function login(User $user)
    {
        $this->client->userLogin($user->email, $user->password);
    }

    /**
     * Create a user represented by User.
     *
     * @param User $user
     *   The user to create, with no UUID provided.
     *
     * @return User
     *   The created user with UUID populated.
     *
     * @throws \Guzzle\Http\Exception\BadResponseException
     */
    public function create(User $user)
    {
        $birthdate_formatted = $user->birthdate->format('Y-m-d');
        $gender_mf = ($user->gender == 'Male' ? 'M' : 'F');
        $response = $this->client->userCreateUser(
            $user->first_name,
            $user->last_name,
            $user->email,
            $gender_mf,
            $birthdate_formatted,
            $user->password);
        $user->uuid = $response->uuid;
        return $user;
    }

    /**
     * Get an user object by email.
     *
     * @param string $email
     *   The email of a user to search the api for.
     *
     * @return User
     *   Returns a user object using the email address provided.
     *
     * @throws \Guzzle\Http\Exception\BadResponseException
     * @throws \AllPlayers\Exceptions\ObjectNotFoundException
     */
    public function getByEmail($email)
    {
        $response = $this->client->usersIndex(array('email' => $email));
        if (empty($response)) {
            throw new ObjectNotFoundException('User with email "' . $email . '" was not found.');
        }
        return $this->decode(array_pop($response));
    }

    /**
     * Use the UUID of a user to get a User typed object.
     *
     * @param string $uuid
     *   The UUID of a user.
     *
     * @return User
     *   Returns a populated User object.
     *
     * @throws \Guzzle\Http\Exception\BadResponseException
     */
    public function get($uuid)
    {
        $user_wannabe = $this->client->userGetUser($uuid);
        return $this->decode($user_wannabe);
    }

    /**
     * Decodes a stdClass object from api to a User typed object.
     *
     * @param stdClass $wannabe
     *   The stdClass object that needs to be changed.
     *
     * @return User
     *   A User object that can be used throughout this service.
     */
    protected function decode(stdClass $wannabe)
    {
        $user = new User();
        $user->first_name = $wannabe->firstname;
        $user->last_name = $wannabe->lastname;
        $user->email = $wannabe->email;
        if (!empty($wannabe->gender)) {
            $user->gender = ($wannabe->gender == 'male' ? 'Male' : 'Female');
        }
        if (!empty($user->birthdate)) {
            $user->birthdate = new DateTime($wannabe->birthday);
        }
        $user->uuid = $wannabe->uuid;
        return $user;
    }
}
