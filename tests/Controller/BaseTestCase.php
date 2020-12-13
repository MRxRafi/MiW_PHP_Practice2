<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Faker\Factory as FakerFactoryAlias;
use Faker\Generator as FakerGeneratorAlias;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * Class BaseTestCase
 *
 * @package App\Tests\Controller
 */
class BaseTestCase extends WebTestCase
{
    private static array $headers;

    protected static KernelBrowser $client;

    protected static FakerGeneratorAlias $faker;

    /** @var array $role_user Role User */
    protected static array $role_user;

    /** @var array $role_admin Role Admin */
    protected static array $role_admin;

    /**
     * This method is called before the first test of this test class is run.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        self::$headers = [];
        self::$client = static::createClient();
        self::$faker = FakerFactoryAlias::create('es_ES');

        // Role user
        self::$role_user = [
            User::EMAIL_ATTR => $_ENV['ROLE_USER_EMAIL'],
            User::PASSWD_ATTR => $_ENV['ROLE_USER_PASSWD'],
        ];

        // Role admin
        self::$role_admin = [
            User::EMAIL_ATTR => $_ENV['ADMIN_USER_EMAIL'],
            User::PASSWD_ATTR => $_ENV['ADMIN_USER_PASSWD'],
        ];

        /** @var EntityManagerInterface $e_manager */
        $e_manager = null;

        try { // Regenera las tablas con todas las entidades mapeadas
            $e_manager = self::bootKernel()
                ->getContainer()
                ->get('doctrine')
                ->getManager();

            $metadata = $e_manager
                ->getMetadataFactory()
                ->getAllMetadata();
            $sch_tool = new SchemaTool($e_manager);
            $sch_tool->dropDatabase();
            $sch_tool->updateSchema($metadata, true);
        } catch (Throwable $e) {
            fwrite(STDERR, 'EXCEPCIÓN: ' . $e->getCode() . ' - ' . $e->getMessage());
            exit(1);
        }

        // Insertar usuarios (roles admin y user)
        $role_admin = new User(
            self::$role_admin[User::EMAIL_ATTR],
            self::$role_admin[User::PASSWD_ATTR],
            [ 'ROLE_ADMIN' ]
        );
        $role_user = new User(
            self::$role_user[User::EMAIL_ATTR],
            self::$role_user[User::PASSWD_ATTR]
        );
        $e_manager->persist($role_admin);
        $e_manager->persist($role_user);
        $e_manager->flush();
    }

    /**
     * Obtiene el JWT directamente de la ruta correspondiente
     * Si recibe como parámetro un nombre de usuario, obtiene un nuevo token
     * Sino, si anteriormente existe el token, lo devuelve
     *
     * @param   null|string  $useremail user email
     * @param   null|string  $password user password
     * @return  array   cabeceras con el token obtenido
     */
    protected function getTokenHeaders(
        ?string $useremail = null,
        ?string $password = null
    ): array {
        if (empty(self::$headers) || null !== $useremail) {
            $data = [
                User::EMAIL_ATTR => $useremail ?? self::$role_admin[User::EMAIL_ATTR],
                User::PASSWD_ATTR => $password ?? self::$role_admin[User::PASSWD_ATTR]
            ];

            self::$client->request(
                Request::METHOD_POST,
                '/api/v1/login_check',
                [ ],
                [ ],
                [ 'CONTENT_TYPE' => 'application/json' ],
                json_encode($data)
            );
            $response = self::$client->getResponse();
            $json_resp = json_decode($response->getContent(), true);
            // (HTTP headers are referenced with a HTTP_ prefix as PHP does)
            self::$headers = [
                'HTTP_ACCEPT'        => 'application/json',
                'HTTP_Authorization' => sprintf('Bearer %s', $json_resp['token']),
            ];
        }

        return self::$headers;
    }
}
