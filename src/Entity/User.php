<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity
 *
 * @Serializer\XmlNamespace(uri="http://www.w3.org/2005/Atom", prefix="atom")
 * @Serializer\AccessorOrder(
 *     "custom",
 *     custom={ "id", "email", "roles", "_links" }
 *     )
 *
 * @Hateoas\Relation(
 *     name="parent",
 *     href="expr(constant('\\App\\Controller\\ApiUsersController::RUTA_API'))"
 * )
 *
 * @Hateoas\Relation(
 *     name="self",
 *     href="expr(constant('\\App\\Controller\\ApiUsersController::RUTA_API') ~ '/' ~ object.getId())"
 * )
 */
class User implements UserInterface
{
    public const USER_ATTR = 'user';
    public const EMAIL_ATTR = 'email';
    public const PASSWD_ATTR = 'password';
    public const ROLES_ATTR = 'roles';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     *
     * @Serializer\XmlAttribute
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     *
     * @Serializer\SerializedName(User::EMAIL_ATTR)
     * @Serializer\XmlElement(cdata=false)
     */
    private string $email;

    /**
     * @ORM\Column(type="json")
     *
     * @Serializer\SerializedName(User::ROLES_ATTR)
     * @Serializer\Accessor(getter="getRoles")
     * @Serializer\XmlElement(cdata=false)
     * @Serializer\XmlList(entry="role")
     */
    private array $roles;

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     *
     * @Serializer\Exclude()
     */
    private string $password;

    /**
     * User constructor.
     * @param string $email
     * @param string $password
     * @param array $roles
     */
    public function __construct(string $email = '', string $password = '', array $roles = [ 'ROLE_USER' ])
    {
        $this->email = $email;
        $this->roles = $roles;
        $this->setPassword($password);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return $this->getEmail();
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $lroles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $lroles[] = 'ROLE_USER';

        return array_unique($lroles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = (string) password_hash($password, PASSWORD_ARGON2ID);

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }
}
