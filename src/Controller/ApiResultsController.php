<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\Result;
use App\Entity\User;
use App\Utility\Utils;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ApiResultsController
 *
 * @package App\Controller
 *
 * @Route(
 *     path=ApiResultsController::RUTA_API,
 *     name="api_results_"
 * )
 */
class ApiResultsController extends AbstractController
{

    public const RUTA_API = '/api/v1/results';

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
     * Summary: Retrieves the collection of Result resources.
     * Notes: Returns all results from the system that the user has access to.
     *
     * @param   Request $request
     * @return  Response
     * @Route(
     *     path=".{_format}/{sort?id}",
     *     defaults={ "_format": "json", "sort": "id" },
     *     requirements={
     *         "sort": "id|result|user",
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
        $resultsRepository = $this->entityManager
            ->getRepository(Result::class);

        if ($this->isGranted(self::ROLE_ADMIN)) {
            $results = $resultsRepository->findBy([], [ $order => 'ASC' ]);
        } else {
            $userId = $this->getUser()->getId();
            $results = $resultsRepository->findBy([User::USER_ATTR => $userId], [ $order => 'ASC' ]);
        }

        $format = Utils::getFormat($request);

        // No hay resultados?
        if (empty($results)) {
            return $this->error404($format);
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            [ 'results' => array_map(fn ($u) =>  [Result::RESULT_ATTR => $u], $results) ],
            $format,
            [
                self::HEADER_CACHE_CONTROL => 'must-revalidate',
                self::HEADER_ETAG => md5(json_encode($results)),
            ]
        );
    }

    /**
     * GET Action
     * Summary: Retrieves a Result resource based on a single ID.
     * Notes: Returns the result identified by &#x60;resultId&#x60;.
     *
     * @param Request $request
     * @param  int $resultId Result id
     * @return Response
     * @Route(
     *     path="/{resultId}.{_format}",
     *     defaults={ "_format": null },
     *     requirements={
     *          "resultId": "\d+",
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
    public function getAction(Request $request, int $resultId): Response
    {
        $result = $this->entityManager
            ->getRepository(Result::class)
            ->find($resultId);

        $format = Utils::getFormat($request);

        if (empty($result)) {
            return $this->error404($format);
        }

        if (!$this->isGranted(self::ROLE_ADMIN)) {
            if($result->getUser()->getId() != $this->getUser()->getId()) {
                throw new HttpException(   // 403
                    Response::HTTP_FORBIDDEN,
                    '`Forbidden`: you don\'t have permission to access'
                );
            }
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            [ Result::RESULT_ATTR => $result ],
            $format,
            [
                self::HEADER_CACHE_CONTROL => 'must-revalidate',
                self::HEADER_ETAG => md5(json_encode($result)),
            ]
        );
    }

    /**
     * Summary: Provides the list of HTTP supported methods
     * Notes: Return a &#x60;Allow&#x60; header with a list of HTTP supported methods.
     *
     * @param  int $resultId Result id
     * @return Response
     * @Route(
     *     path="/{resultId}.{_format}",
     *     defaults={ "resultId" = 0, "_format": "json" },
     *     requirements={
     *          "resultId": "\d+",
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_OPTIONS },
     *     name="options"
     * )
     */
    public function optionsAction(int $resultId): Response
    {
        $methods = $resultId
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
     * Summary: Removes the Result resource.
     * Notes: Deletes the result identified by &#x60;resultId&#x60;.
     *
     * @param   Request $request
     * @param   int $resultId Result id
     * @return  Response
     * @Route(
     *     path="/{resultId}.{_format}",
     *     defaults={ "_format": null },
     *     requirements={
     *          "resultId": "\d+",
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
    public function deleteAction(Request $request, int $resultId): Response
    {
        $result = $this->entityManager
            ->getRepository(Result::class)
            ->find($resultId);
        $format = Utils::getFormat($request);

        if (null === $result) {   // 404 - Not Found
            return $this->error404($format);
        }

        // Puede borrar un resultado sólo si tiene ROLE_ADMIN o es un resultado del User
        if ($this->getUser()->getId() != $result->getUser()->getId()) {
            if(!$this->isGranted(self::ROLE_ADMIN)){
                throw new HttpException(   // 403
                    Response::HTTP_FORBIDDEN,
                    '`Forbidden`: you don\'t have permission to access'
                );
            }
        }

        $this->entityManager->remove($result);
        $this->entityManager->flush();

        return Utils::apiResponse(Response::HTTP_NO_CONTENT);
    }

    /**
     * POST action
     * Summary: Creates a Result resource.
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
        $body = $request->getContent();
        $postData = json_decode($body, true);
        $format = Utils::getFormat($request);

        if (!isset($postData[Result::RESULT_ATTR], $postData[Result::USER_ATTR])) {
            // 422 - Unprocessable Entity -> Faltan datos
            $message = new Message(Response::HTTP_UNPROCESSABLE_ENTITY, Response::$statusTexts[422]);
            return Utils::apiResponse(
                $message->getCode(),
                $message,
                $format
            );
        }

        // hay datos -> procesarlos
        $result_exist = $this->entityManager
                ->getRepository(Result::class)
                ->findOneBy([ Result::RESULT_ATTR => $postData[Result::RESULT_ATTR] ]);

        $user_exist = $this->entityManager
            ->getRepository(User::class)
            ->find($postData[Result::USER_ATTR]);

        if (null !== $result_exist || null === $user_exist) {    // 400 - Bad Request
            $message = new Message(Response::HTTP_BAD_REQUEST, Response::$statusTexts[400]);
            return Utils::apiResponse(
                $message->getCode(),
                $message,
                $format
            );
        }

        // 201 - Created
        if (isset($postData[Result::TIME_ATTR])) {
            $newDateTime = new DateTime($postData[Result::TIME_ATTR]);
        } else {
            $newDateTime = new DateTime('now');
        }

        $result = new Result(
            $postData[Result::RESULT_ATTR],
            $user_exist,
            $newDateTime
        );

        $this->entityManager->persist($result);
        $this->entityManager->flush();

        return Utils::apiResponse(
            Response::HTTP_CREATED,
            [ Result::RESULT_ATTR => $result ],
            $format,
            [
                'Location' => self::RUTA_API . '/' . $result->getId(),
            ]
        );
    }

    /**
     * PUT action
     * Summary: Updates the Result resource.
     * Notes: Updates the result identified by &#x60;resultId&#x60;.
     *
     * @param   Request $request request
     * @param   int $resultId Result id
     * @return  Response
     * @Route(
     *     path="/{resultId}.{_format}",
     *     defaults={ "_format": null },
     *     requirements={
     *          "resultId": "\d+",
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
    public function putAction(Request $request, int $resultId): Response
    {
        $result = $this->entityManager
            ->getRepository(Result::class)
            ->find($resultId);

        $format = Utils::getFormat($request);

        if (null === $result) {    // 404 - Not Found
            return $this->error404($format);
        }

        // Puede editar otro usuario diferente sólo si tiene ROLE_ADMIN
        if ((self::getUser()->getId() !== $result->getUser()->getId())
            && !$this->isGranted(self::ROLE_ADMIN)) {
            throw new HttpException(   // 403
                Response::HTTP_FORBIDDEN,
                "`Forbidden`: you don't have permission to access"
            );
        }
        $body = $request->getContent();
        $postData = json_decode($body, true);

        // result
        if (isset($postData[Result::RESULT_ATTR])) {
            $result_exist = $this->entityManager
                ->getRepository(Result::class)
                ->findOneBy([ Result::RESULT_ATTR => $postData[Result::RESULT_ATTR] ]);

            if (null !== $result_exist) {    // 400 - Bad Request
                $message = new Message(Response::HTTP_BAD_REQUEST, Response::$statusTexts[400]);
                return Utils::apiResponse(
                    $message->getCode(),
                    $message,
                    $format
                );
            }
            $result->setResult($postData[Result::RESULT_ATTR]);
        }

        // user
        if (isset($postData[Result::USER_ATTR])) {
            $user_exist = $this->entityManager
                ->getRepository(User::class)
                ->find($postData[Result::USER_ATTR]);

            if(null === $user_exist) {
                $message = new Message(Response::HTTP_BAD_REQUEST, Response::$statusTexts[400]);
                return Utils::apiResponse(
                    $message->getCode(),
                    $message,
                    $format
                );
            }

            $result->setUser($user_exist);
        }

        // time
        if (isset($postData[Result::TIME_ATTR])) {
            $result->setTime(new DateTime($postData[Result::TIME_ATTR]));
        }

        $this->entityManager->flush();

        return Utils::apiResponse(
            209,                        // 209 - Content Returned
            [ Result::RESULT_ATTR => $result ],
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
