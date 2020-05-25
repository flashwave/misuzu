<?php
namespace Misuzu;

use Misuzu\Users\User;
use Misuzu\Users\UserSession;

class AuthToken {
    public const VERSION = 1;
    public const WIDTH = 37;

    private $userId = -1;
    private $sessionToken = '';

    private $user = null;
    private $session = null;

    public function isValid(): bool {
        return $this->getUserId() > 0
            && !empty($this->getSessionToken());
    }

    public function getUserId(): int {
        return $this->userId < 1 ? -1 : $this->userId;
    }
    public function setUserId(int $userId): self {
        $this->user = null;
        $this->userId = $userId;
        return $this;
    }
    public function getUser(): User {
        if($this->user === null)
            $this->user = User::byId($this->getUserId());
        return $this->user;
    }
    public function setUser(User $user): self {
        $this->user = $user;
        $this->userId = $user->getId();
        return $this;
    }

    public function getSessionToken(): string {
        return $this->sessionToken ?? '';
    }
    public function setSessionToken(string $token): self {
        $this->session = null;
        $this->sessionToken = $token;
        return $this;
    }
    public function getSession(): UserSession {
        if($this->session === null)
            $this->session = UserSession::byToken($this->getSessionToken());
        return $this->session;
    }
    public function setSession(UserSession $session): self {
        $this->session = $session;
        $this->sessionToken = $session->getToken();
        return $this;
    }

    public function pack(bool $base64 = true): string {
        $packed = pack('CNH*', self::VERSION, $this->getUserId(), $this->getSessionToken());
        if($base64)
            $packed = Base64::encode($packed, true);
        return $packed;
    }

    public static function unpack(string $data, bool $base64 = true): self {
        $obj = new static;

        if(empty($data))
            return $obj;
        if($base64)
            $data = Base64::decode($data, true);

        $data = str_pad($data, self::WIDTH, "\x00");
        $data = unpack('Cversion/Nuser/H*token', $data);

        if($data['version'] >= 1)
            $obj->setUserId($data['user'])
                ->setSessionToken($data['token']);

        return $obj;
    }

    public static function create(User $user, UserSession $session): self {
        return (new static)
            ->setUser($user)
            ->setSession($session);
    }
}
