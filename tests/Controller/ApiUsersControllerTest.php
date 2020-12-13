<?php

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Entity\User;
use Faker\Factory as FakerFactoryAlias;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ApiUsersControllerTest
 *
 * @package App\Tests\Controller
 * @group   controllers
 *
 * @coversDefaultClass \App\Controller\ApiUsersController
 */
class ApiUsersControllerTest extends BaseTestCase
{
    private const RUTA_API = '/api/v1/users';

    /**
     * Test OPTIONS /users[/userId] 204 No Content
     *
     * @covers ::__construct
     * @covers ::optionsAction
     * @return void
     */
    public function testOptionsUserAction204NoContent(): void
    {
        // OPTIONS /api/v1/users
        self::$client->request(
            Request::METHOD_OPTIONS,
            self::RUTA_API
        );
        $response = self::$client->getResponse();

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            $response->getStatusCode()
        );
        self::assertNotEmpty($response->headers->get('Allow'));

        // OPTIONS /api/v1/users/{id}
        self::$client->request(
            Request::METHOD_OPTIONS,
            self::RUTA_API . '/' . self::$faker->numberBetween(1, 100)
        );

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            $response->getStatusCode()
        );
        self::assertNotEmpty($response->headers->get('Allow'));
    }

    /**
     * Test GET /users 404 Not Found
     *
     * @return void
     */
//    public function testCGetAction404(): void
//    {
//        $headers = [];
//        self::$client->request(
//            Request::METHOD_GET,
//            self::RUTA_API,
//            [],
//            [],
//            $headers
//        );
//        $response = self::$client->getResponse();
//
//        self::assertEquals(
//            Response::HTTP_NOT_FOUND,
//            $response->getStatusCode()
//        );
//        $r_body = (string) $response->getContent();
//        self::assertContains(Message::CODE_ATTR, $r_body);
//        self::assertContains(Message::MESSAGE_ATTR, $r_body);
//        $r_data = json_decode($r_body, true);
//        self::assertEquals(Response::HTTP_NOT_FOUND, $r_data[Message::MESSAGE_ATTR][Message::CODE_ATTR]);
//        self::assertEquals(Response::$statusTexts[404], $r_data[Message::MESSAGE_ATTR][Message::MESSAGE_ATTR]);
//    }

    /**
     * Test POST /users 201 Created
     *
     * @return array user data
     */
    public function testPostUserAction201Created(): array
    {
        $role = self::$faker->word;
        $p_data = [
            User::EMAIL_ATTR => self::$faker->email,
            User::PASSWD_ATTR => self::$faker->password,
            User::ROLES_ATTR => [ $role ],
        ];

        // 201
        $headers = $this->getTokenHeaders();
        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API,
            [],
            [],
            $headers,
            json_encode($p_data)
        );
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertTrue($response->isSuccessful());
        self::assertNotNull($response->headers->get('Location'));
        self::assertJson($response->getContent());
        $user = json_decode($response->getContent(), true);
        self::assertNotEmpty($user['user']['id']);
        self::assertSame($p_data[User::EMAIL_ATTR], $user['user'][User::EMAIL_ATTR]);
        self::assertContains(
            $role,
            $user['user'][User::ROLES_ATTR]
        );

        return $user['user'];
    }

    /**
     * Test GET /users 200 Ok
     *
     * @return void
     * @depends testPostUserAction201Created
     */
    public function testCGetAction200Ok(): void
    {
        $headers = $this->getTokenHeaders();
        self::$client->request(Request::METHOD_GET, self::RUTA_API, [], [], $headers);
        $response = self::$client->getResponse();
        self::assertTrue($response->isSuccessful());
        self::assertNotNull($response->getEtag());
        self::assertJson($response->getContent());
        $users = json_decode($response->getContent(), true);
        self::assertArrayHasKey('users', $users);
    }

    /**
     * Test GET /users 200 Ok (XML)
     *
     * @param   array $user user returned by testPostUserAction201()
     * @return  void
     * @depends testPostUserAction201Created
     */
    public function testCGetAction200XmlOk(array $user): void
    {
        $headers = $this->getTokenHeaders();
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/' . $user['id'] . '.xml',
            [],
            [],
            $headers
        );
        $response = self::$client->getResponse();
        self::assertTrue($response->isSuccessful());
        self::assertNotNull($response->getEtag());
        self::assertTrue($response->headers->contains('content-type', 'application/xml'));
    }

    /**
     * Test GET /users/{userId} 200 Ok
     *
     * @param   array $user user returned by testPostUserAction201()
     * @return  void
     * @depends testPostUserAction201Created
     */
    public function testGetUserAction200Ok(array $user): void
    {
        $headers = $this->getTokenHeaders();
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/' . $user['id'],
            [],
            [],
            $headers
        );
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertNotNull($response->getEtag());
        self::assertJson((string) $response->getContent());
        $user_aux = json_decode((string) $response->getContent(), true);
        self::assertSame($user['id'], $user_aux['user']['id']);
    }

    /**
     * Test POST /users 400 Bad Request
     *
     * @param   array $user user returned by testPostUserAction201()
     * @return  void
     * @depends testPostUserAction201Created
     */
    public function testPostUserAction400BadRequest(array $user): void
    {
        $headers = $this->getTokenHeaders();

        $p_data = [
            User::EMAIL_ATTR => $user[User::EMAIL_ATTR], // mismo e-mail
            User::PASSWD_ATTR => self::$faker->password,
        ];
        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API,
            [],
            [],
            $headers,
            json_encode($p_data)
        );
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $r_body = (string) $response->getContent();
        self::assertJson($r_body);
        self::assertStringContainsString(Message::CODE_ATTR, $r_body);
        self::assertStringContainsString(Message::MESSAGE_ATTR, $r_body);
        $r_data = json_decode($r_body, true);
        self::assertSame(Response::HTTP_BAD_REQUEST, $r_data[Message::CODE_ATTR]);
        self::assertSame(
            Response::$statusTexts[400],
            $r_data[Message::MESSAGE_ATTR]
        );
    }

    /**
     * Test PUT /users/{userId} 209 Content Returned
     *
     * @param   array $user user returned by testPostUserAction201()
     * @return  array modified user data
     * @depends testPostUserAction201Created
     */
    public function testPutUserAction209ContentReturned(array $user): array
    {
        $headers = $this->getTokenHeaders();
        $role = self::$faker->word;
        $p_data = [
            User::EMAIL_ATTR => self::$faker->email,
            User::PASSWD_ATTR => self::$faker->password,
            User::ROLES_ATTR => [ $role ],
        ];

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $user['id'],
            [],
            [],
            $headers,
            json_encode($p_data)
        );
        $response = self::$client->getResponse();

        self::assertSame(209, $response->getStatusCode());
        self::assertJson((string) $response->getContent());
        $user_aux = json_decode((string) $response->getContent(), true);
        self::assertSame($user['id'], $user_aux['user']['id']);
        self::assertSame($p_data[User::EMAIL_ATTR], $user_aux['user'][User::EMAIL_ATTR]);
        self::assertContains(
            $role,
            $user_aux['user'][User::ROLES_ATTR]
        );

        return $user_aux['user'];
    }

    /**
     * Test PUT /users/{userId} 400 Bad Request
     *
     * @param   array $user user returned by testPutUserAction209()
     * @return  void
     * @depends testPutUserAction209ContentReturned
     */
    public function testPutUserAction400BadRequest(array $user): void
    {
        $headers = $this->getTokenHeaders();
        // e-mail already exists
        $p_data = [
            User::EMAIL_ATTR => $user[User::EMAIL_ATTR]
        ];
        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $user['id'],
            [],
            [],
            $headers,
            json_encode($p_data)
        );
        $response = self::$client->getResponse();

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode()
        );
        $r_body = (string) $response->getContent();
        self::assertJson($r_body);
        self::assertStringContainsString(Message::CODE_ATTR, $r_body);
        self::assertStringContainsString(Message::MESSAGE_ATTR, $r_body);
        $r_data = json_decode($r_body, true);
        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $r_data[Message::CODE_ATTR]
        );
        self::assertSame(
            Response::$statusTexts[400],
            $r_data[Message::MESSAGE_ATTR]
        );
    }

    /**
     * Test DELETE /users/{userId} 204 No Content
     *
     * @param   array $user user returned by testPostUserAction201()
     * @return  int userId
     * @depends testPostUserAction201Created
     * @depends testPostUserAction400BadRequest
     * @depends testGetUserAction200Ok
     * @depends testPutUserAction400BadRequest
     */
    public function testDeleteUserAction204NoContent(array $user): int
    {
        $headers = $this->getTokenHeaders();
        self::$client->request(
            Request::METHOD_DELETE,
            self::RUTA_API . '/' . $user['id'],
            [],
            [],
            $headers
        );
        $response = self::$client->getResponse();

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            $response->getStatusCode()
        );
        self::assertEmpty((string) $response->getContent());

        return $user['id'];
    }

    /**
     * Test POST /users 422 Unprocessable Entity
     *
     * @param null|string $email
     * @param null|string $password
     * @dataProvider userProvider422
     * @return void
     */
    public function testPostUserAction422UnprocessableEntity(?string $email, ?string $password): void
    {
        $headers = $this->getTokenHeaders();
        $p_data = [
            User::EMAIL_ATTR => $email,
            User::PASSWD_ATTR => $password
        ];

        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API,
            [],
            [],
            $headers,
            json_encode($p_data)
        );
        $response = self::$client->getResponse();

        self::assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $response->getStatusCode()
        );
        $r_body = (string) $response->getContent();
        self::assertJson($r_body);
        self::assertStringContainsString(Message::CODE_ATTR, $r_body);
        self::assertStringContainsString(Message::MESSAGE_ATTR, $r_body);
        $r_data = json_decode($r_body, true);
        self::assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $r_data[Message::CODE_ATTR]
        );
        self::assertSame(
            Response::$statusTexts[422],
            $r_data[Message::MESSAGE_ATTR]
        );
    }

    /**
     * Test GET    /users 401 UNAUTHORIZED
     * Test POST   /users 401 UNAUTHORIZED
     * Test GET    /users/{userId} 401 UNAUTHORIZED
     * Test PUT    /users/{userId} 401 UNAUTHORIZED
     * Test DELETE /users/{userId} 401 UNAUTHORIZED
     *
     * @param string $method
     * @param string $uri
     * @dataProvider routeProvider401()
     * @return void
     * @uses \App\EventListener\ExceptionListener
     */
    public function testUserStatus401Unauthorized(string $method, string $uri): void
    {
        self::$client->request(
            $method,
            $uri,
            [],
            [],
            [ 'HTTP_ACCEPT' => 'application/json' ]
        );
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertJson((string) $response->getContent());
        $r_body = (string) $response->getContent();
        self::assertStringContainsString(Message::CODE_ATTR, $r_body);
        self::assertStringContainsString(Message::MESSAGE_ATTR, $r_body);
        $r_data = json_decode($r_body, true);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $r_data[Message::CODE_ATTR]);
        self::assertContains(
            Response::$statusTexts[401],
            $r_data[Message::MESSAGE_ATTR]
        );
    }

    /**
     * Test GET    /users/{userId} 404 NOT FOUND
     * Test PUT    /users/{userId} 404 NOT FOUND
     * Test DELETE /users/{userId} 404 NOT FOUND
     *
     * @param string $method
     * @param int $userId user id. returned by testDeleteUserAction204()
     * @dataProvider routeProvider404
     * @return void
     * @depends      testDeleteUserAction204NoContent
     */
    public function testUserStatus404NotFound(string $method, int $userId): void
    {
        $headers = $this->getTokenHeaders(
            self::$role_admin[User::EMAIL_ATTR],
            self::$role_admin[User::PASSWD_ATTR]
        );
        self::$client->request(
            $method,
            self::RUTA_API . '/' . $userId,
            [],
            [],
            $headers
        );
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $r_body = (string) $response->getContent();
        self::assertStringContainsString(Message::CODE_ATTR, $r_body);
        self::assertStringContainsString(Message::MESSAGE_ATTR, $r_body);
        $r_data = json_decode($r_body, true);
        self::assertSame(Response::HTTP_NOT_FOUND, $r_data[Message::CODE_ATTR]);
        self::assertSame(Response::$statusTexts[404], $r_data[Message::MESSAGE_ATTR]);
    }

    /**
     * Test POST   /users 403 FORBIDDEN
     * Test PUT    /users/{userId} 403 FORBIDDEN
     * Test DELETE /users/{userId} 403 FORBIDDEN
     *
     * @param string $method
     * @param string $uri
     * @dataProvider routeProvider403()
     * @return void
     * @uses \App\EventListener\ExceptionListener
     */
    public function testUserStatus403Forbidden(string $method, string $uri): void
    {
        $headers = $this->getTokenHeaders(
            self::$role_user[User::EMAIL_ATTR],
            self::$role_user[User::PASSWD_ATTR]
        );
        self::$client->request($method, $uri, [], [], $headers);
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertJson((string) $response->getContent());
        $r_body = (string) $response->getContent();
        self::assertStringContainsString(Message::CODE_ATTR, $r_body);
        self::assertStringContainsString(Message::MESSAGE_ATTR, $r_body);
        $r_data = json_decode($r_body, true);
        self::assertSame(Response::HTTP_FORBIDDEN, $r_data[Message::CODE_ATTR]);
        self::assertSame(
            '`Forbidden`: you don\'t have permission to access',
            $r_data[Message::MESSAGE_ATTR]
        );
    }

    /**
     * *********
     * PROVIDERS
     * *********
     */

    /**
     * User provider (incomplete) -> 422 status code
     *
     * @return array user data
     */
    public function userProvider422(): array
    {
        $faker = FakerFactoryAlias::create('es_ES');
        $email = $faker->email;
        $password = $faker->password;

        return [
            'no_email'  => [ null,   $password ],
            'no_passwd' => [ $email, null      ],
            'nothing'   => [ null,   null      ],
        ];
    }

    /**
     * Route provider (expected status: 401 UNAUTHORIZED)
     *
     * @return array [ method, url ]
     */
    public function routeProvider401(): array
    {
        return [
            'cgetAction401'   => [ Request::METHOD_GET,    self::RUTA_API ],
            'getAction401'    => [ Request::METHOD_GET,    self::RUTA_API . '/1' ],
            'postAction401'   => [ Request::METHOD_POST,   self::RUTA_API ],
            'putAction401'    => [ Request::METHOD_PUT,    self::RUTA_API . '/1' ],
            'deleteAction401' => [ Request::METHOD_DELETE, self::RUTA_API . '/1' ],
        ];
    }

    /**
     * Route provider (expected status 404 NOT FOUND)
     *
     * @return array [ method ]
     */
    public function routeProvider404(): array
    {
        return [
            'getAction404'    => [ Request::METHOD_GET ],
            'putAction404'    => [ Request::METHOD_PUT ],
            'deleteAction404' => [ Request::METHOD_DELETE ],
        ];
    }

    /**
     * Route provider (expected status: 403 FORBIDDEN)
     *
     * @return array [ method, url ]
     */
    public function routeProvider403(): array
    {
        return [
            'postAction403'   => [ Request::METHOD_POST,   self::RUTA_API ],
            'putAction403'    => [ Request::METHOD_PUT,    self::RUTA_API . '/1' ],
            'deleteAction403' => [ Request::METHOD_DELETE, self::RUTA_API . '/1' ],
        ];
    }
}
