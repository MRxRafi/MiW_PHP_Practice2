<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Utility\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ApiUsersController
 *
 * @package App\Controller
 *
 * @Route(
 *     path=ApiUsersController::RUTA_API,
 *     name="api_users_"
 * )
 */
class ApiUsersController extends AbstractController
{

    public const RUTA_API = '/api/v1/users';

    private const HEADER_CACHE_CONTROL = 'Cache-Control';
    private const HEADER_ETAG = 'ETag';
    private const HEADER_ALLOW = 'Allow';
    private const ROLE_ADMIN = 'ROLE_ADMIN';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entityManager = $em;
    }

    /**
     * CGET Action
     * Summary: Retrieves the collection of User resources.
     * Notes: Returns all users from the system that the user has access to.
     *
     * @param   Request $request
     * @return  Response
     * @Route(
     *     path=".{_format}/{sort?id}",
     *     defaults={ "_format": "json", "sort": "id" },
     *     requirements={
     *         "sort": "id|email|roles",
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_GET },
     *     name="cget"
     * )
     *
     * @Security(
     *     expression="is_granted('IS_AUTHENTICATED_FULLY')",
     *     statusCode=401,
     *     message="`Unauthorized`: Invalid credentials."
     * )
     */
    public function cgetAction(Request $request): Response
    {
        $order = $request->get('sort');
        $users = $this->entityManager
            ->getRepository(User::class)
            ->findBy([], [ $order => 'ASC' ]);
        $format = Utils::getFormat($request);

        // No hay usuarios?
        if (empty($users)) {
            return $this->error404($format);
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            [ 'users' => array_map(fn ($u) =>  ['user' => $u], $users) ],
            $format,
            [
                self::HEADER_CACHE_CONTROL => 'must-revalidate',
                self::HEADER_ETAG => md5(json_encode($users)),
            ]
        );
    }

    /**
     * GET Action
     * Summary: Retrieves a User resource based on a single ID.
     * Notes: Returns the user identified by &#x60;userId&#x60;.
     *
     * @param Request $request
     * @param  int $userId User id
     * @return Response
     * @Route(
     *     path="/{userId}.{_format}",
     *     defaults={ "_format": null },
     *     requirements={
     *          "userId": "\d+",
     *          "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_GET },
     *     name="get"
     * )
     *
     * @Security(
     *     expression="is_granted('IS_AUTHENTICATED_FULLY')",
     *     statusCode=401,
     *     message="`Unauthorized`: Invalid credentials."
     * )
     */
    public function getAction(Request $request, int $userId): Response
    {
        $user = $this->entityManager
            ->getRepository(User::class)
            ->find($userId);
        $format = Utils::getFormat($request);

        if (empty($user)) {
            return $this->error404($format);
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            [ User::USER_ATTR => $user ],
            $format,
            [
                self::HEADER_CACHE_CONTROL => 'must-revalidate',
                self::HEADER_ETAG => md5(json_encode($user)),
            ]
        );
    }

    /**
     * Summary: Provides the list of HTTP supported methods
     * Notes: Return a &#x60;Allow&#x60; header with a list of HTTP supported methods.
     *
     * @param  int $userId User id
     * @return Response
     * @Route(
     *     path="/{userId}.{_format}",
     *     defaults={ "userId" = 0, "_format": "json" },
     *     requirements={
     *          "userId": "\d+",
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_OPTIONS },
     *     name="options"
     * )
     */
    public function optionsAction(int $userId): Response
    {
        $methods = $userId
            ? [ Request::METHOD_GET, Request::METHOD_PUT, Request::METHOD_DELETE ]
            : [ Request::METHOD_GET, Request::METHOD_POST ];
        $methods[] = Request::METHOD_OPTIONS;

        return new JsonResponse(
            null,
            Response::HTTP_NO_CONTENT,
            [
                self::HEADER_ALLOW => implode(', ', $methods),
                self::HEADER_CACHE_CONTROL => 'public, inmutable'
            ]
        );
    }

    /**
     * DELETE Action
     * Summary: Removes the User resource.
     * Notes: Deletes the user identified by &#x60;userId&#x60;.
     *
     * @param   Request $request
     * @param   int $userId User id
     * @return  Response
     * @Route(
     *     path="/{userId}.{_format}",
     *     defaults={ "_format": null },
     *     requirements={
     *          "userId": "\d+",
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_DELETE },
     *     name="delete"
     * )
     *
     * @Security(
     *     expression="is_granted('IS_AUTHENTICATED_FULLY')",
     *     statusCode=401,
     *     message="`Unauthorized`: Invalid credentials."
     * )
     */
    public function deleteAction(Request $request, int $userId): Response
    {
        // Puede crear un usuario sólo si tiene ROLE_ADMIN
        if (!$this->isGranted(self::ROLE_ADMIN)) {
            throw new HttpException(   // 403
                Response::HTTP_FORBIDDEN,
                '`Forbidden`: you don\'t have permission to access'
            );
        }
        $format = Utils::getFormat($request);

        $user = $this->entityManager
            ->getRepository(User::class)
            ->find($userId);

        if (null === $user) {   // 404 - Not Found
            return $this->error404($format);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return Utils::apiResponse(Response::HTTP_NO_CONTENT);
    }

    /**
     * POST action
     * Summary: Creates a User resource.
     *
     * @param Request $request request
     * @return Response
     * @Route(
     *     path=".{_format}",
     *     defaults={ "_format": null },
     *     requirements={
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_POST },
     *     name="post"
     * )
     *
     * @Security(
     *     expression="is_granted('IS_AUTHENTICATED_FULLY')",
     *     statusCode=401,
     *     message="`Unauthorized`: Invalid credentials."
     * )
     */
    public function postAction(Request $request): Response
    {
        // Puede crear un usuario sólo si tiene ROLE_ADMIN
        if (!$this->isGranted(self::ROLE_ADMIN)) {
            throw new HttpException(   // 403
                Response::HTTP_FORBIDDEN,
                '`Forbidden`: you don\'t have permission to access'
            );
        }
        $body = $request->getContent();
        $postData = json_decode($body, true);
        $format = Utils::getFormat($request);

        if (!isset($postData[User::EMAIL_ATTR], $postData[User::PASSWD_ATTR])) {
            // 422 - Unprocessable Entity -> Faltan datos
            $message = new Message(Response::HTTP_UNPROCESSABLE_ENTITY, Response::$statusTexts[422]);
            return Utils::apiResponse(
                $message->getCode(),
                $message,
                $format
            );
        }

        // hay datos -> procesarlos
        $user_exist = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy([ User::EMAIL_ATTR => $postData[User::EMAIL_ATTR] ]);

        if (null !== $user_exist) {    // 400 - Bad Request
            $message = new Message(Response::HTTP_BAD_REQUEST, Response::$statusTexts[400]);
            return Utils::apiResponse(
                $message->getCode(),
                $message,
                $format
            );
        }

        // 201 - Created
        $user = new User(
            $postData[User::EMAIL_ATTR],
            $postData[User::PASSWD_ATTR]
        );
        // roles
        if (isset($postData[User::ROLES_ATTR])) {
            $user->setRoles($postData[User::ROLES_ATTR] ?? []);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return Utils::apiResponse(
            Response::HTTP_CREATED,
            [ User::USER_ATTR => $user ],
            $format,
            [
                'Location' => self::RUTA_API . '/' . $user->getId(),
            ]
        );
    }

    /**
     * PUT action
     * Summary: Updates the User resource.
     * Notes: Updates the user identified by &#x60;userId&#x60;.
     *
     * @param   Request $request request
     * @param   int $userId User id
     * @return  Response
     * @Route(
     *     path="/{userId}.{_format}",
     *     defaults={ "_format": null },
     *     requirements={
     *          "userId": "\d+",
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_PUT },
     *     name="put"
     * )
     *
     * @Security(
     *     expression="is_granted('IS_AUTHENTICATED_FULLY')",
     *     statusCode=401,
     *     message="`Unauthorized`: Invalid credentials."
     * )
     */
    public function putAction(Request $request, int $userId): Response
    {
        // Puede editar otro usuario diferente sólo si tiene ROLE_ADMIN
        if (($this->getUser()->getId() !== $userId)
            && !$this->isGranted(self::ROLE_ADMIN)) {
            throw new HttpException(   // 403
                Response::HTTP_FORBIDDEN,
                "`Forbidden`: you don't have permission to access"
            );
        }
        $body = $request->getContent();
        $postData = json_decode($body, true);
        $format = Utils::getFormat($request);

        $user = $this->entityManager
            ->getRepository(User::class)
            ->find($userId);

        if (null === $user) {    // 404 - Not Found
            return $this->error404($format);
        }

        if (isset($postData[User::EMAIL_ATTR])) {
            $user_exist = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy([ User::EMAIL_ATTR => $postData[User::EMAIL_ATTR] ]);

            if (null !== $user_exist) {    // 400 - Bad Request
                $message = new Message(Response::HTTP_BAD_REQUEST, Response::$statusTexts[400]);
                return Utils::apiResponse(
                    $message->getCode(),
                    $message,
                    $format
                );
            }
            $user->setEmail($postData[User::EMAIL_ATTR]);
        }

        // password
        if (isset($postData[User::PASSWD_ATTR])) {
            $user->setPassword($postData[User::PASSWD_ATTR]);
        }

        // roles
        if (isset($postData[User::ROLES_ATTR])) {
            if (in_array('ROLE_ADMIN', $postData[User::ROLES_ATTR], true)
                && !$this->isGranted(self::ROLE_ADMIN)) {
                throw new HttpException(   // 403
                    Response::HTTP_FORBIDDEN,
                    "`Forbidden`: you don't have permission to access"
                );
            }
            $user->setRoles($postData[User::ROLES_ATTR]);
        }

        $this->entityManager->flush();

        return Utils::apiResponse(
            209,                        // 209 - Content Returned
            [ User::USER_ATTR => $user ],
            $format
        );
    }

    /**
     * Response 404 Not Found
     * @param string $format
     *
     * @return Response
     */
    private function error404(string $format): Response
    {
        $message = new Message(Response::HTTP_NOT_FOUND, Response::$statusTexts[404]);
        return Utils::apiResponse(
            $message->getCode(),
            $message,
            $format
        );
    }
}
