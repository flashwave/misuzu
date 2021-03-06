<?php
namespace Misuzu\Http\Handlers;

use HttpResponse;
use HttpRequest;
use Misuzu\AuthToken;
use Misuzu\Base64;
use Misuzu\Config;
use Misuzu\DB;
use Misuzu\Emoticon;
use Misuzu\Stream;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;
use Misuzu\Users\UserChatToken;
use Misuzu\Users\UserChatTokenNotFoundException;
use Misuzu\Users\UserChatTokenCreationFailedException;
use Misuzu\Users\UserSession;
use Misuzu\Users\UserSessionNotFoundException;
use Misuzu\Users\UserWarning;
use Misuzu\Users\UserWarningCreationFailedException;

final class SockChatHandler extends Handler {
    private string $hashKey = 'woomy';

    private const P_KICK_USER      = 0x00000001;
    private const P_BAN_USER       = 0x00000002;
    private const P_SILENCE_USER   = 0x00000004;
    private const P_BROADCAST      = 0x00000008;
    private const P_SET_OWN_NICK   = 0x00000010;
    private const P_SET_OTHER_NICK = 0x00000020;
    private const P_CREATE_CHANNEL = 0x00000040;
    private const P_DELETE_CHANNEL = 0x00010000;
    private const P_SET_CHAN_PERMA = 0x00000080;
    private const P_SET_CHAN_PASS  = 0x00000100;
    private const P_SET_CHAN_HIER  = 0x00000200;
    private const P_JOIN_ANY_CHAN  = 0x00020000;
    private const P_SEND_MESSAGE   = 0x00000400;
    private const P_DELETE_OWN_MSG = 0x00000800;
    private const P_DELETE_ANY_MSG = 0x00001000;
    private const P_EDIT_OWN_MSG   = 0x00002000;
    private const P_EDIT_ANY_MSG   = 0x00004000;
    private const P_VIEW_IP_ADDR   = 0x00008000;

    private const PERMS_DEFAULT = self::P_SEND_MESSAGE | self::P_DELETE_OWN_MSG | self::P_EDIT_OWN_MSG;
    private const PERMS_MANAGE_USERS = self::P_SET_OWN_NICK | self::P_SET_OTHER_NICK | self::P_DELETE_ANY_MSG
                                        | self::P_EDIT_ANY_MSG | self::P_VIEW_IP_ADDR | self::P_BROADCAST;
    private const PERMS_MANAGE_WARNS = self::P_KICK_USER | self::P_BAN_USER | self::P_SILENCE_USER;
    private const PERMS_CHANGE_BACKG = self::P_SET_OWN_NICK | self::P_CREATE_CHANNEL | self::P_SET_CHAN_PASS;
    private const PERMS_MANAGE_FORUM = self::P_CREATE_CHANNEL | self::P_SET_CHAN_PERMA | self::P_SET_CHAN_PASS
                                        | self::P_SET_CHAN_HIER | self::P_DELETE_CHANNEL | self::P_JOIN_ANY_CHAN;

    public function __construct() {
        $hashKeyPath = Config::get('sockChat.hashKeyPath', Config::TYPE_STR, '');

        if(is_file($hashKeyPath))
            $this->hashKey = file_get_contents($hashKeyPath);

        $GLOBALS['misuzuBypassLockdown'] = true;
        parent::__construct();
    }

    public function phpFile(HttpResponse $response, HttpRequest $request) {
        $query = $request->getQueryParams();

        if(isset($query['emotes']))
            return $this->emotes($response, $request);

        if(isset($query['bans']) && is_string($query['bans']))
            return $this->bans($response, $request->setHeader('X-SharpChat-Signature', $query['bans']));

        $body = $request->getParsedBody();

        if(isset($body['bump'], $body['hash']) && is_string($body['bump']) && is_string($body['hash']))
            return $this->bump(
                $response,
                $request->setHeader('X-SharpChat-Signature', $body['hash'])
                    ->setBody(Stream::create($body['bump']))
            );

        $source = isset($body['user_id']) ? $body : $query;

        if(isset($source['user_id'], $source['token'], $source['ip'], $source['hash'])
            && is_string($source['user_id']) && is_string($source['token'])
            && is_string($source['ip']) && is_string($source['hash']))
            return $this->verify(
                $response,
                $request->setHeader('X-SharpChat-Signature', $source['hash'])
                    ->setBody(Stream::create(json_encode([
                        'user_id' => $source['user_id'],
                        'token' => $source['token'],
                        'ip' => $source['ip'],
                    ])))
            );

        return $this->login($response, $request);
    }

    public function emotes(HttpResponse $response, HttpRequest $request): array {
        $response->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET');

        $raw = Emoticon::all();
        $out = [];

        foreach($raw as $emote) {
            $strings = [];

            foreach($emote->getStrings() as $string) {
                $strings[] = sprintf(':%s:', $string->emote_string);
            }

            $out[] = [
                'Text' => $strings,
                'Image' => $emote->getUrl(),
                'Hierarchy' => $emote->getRank(),
            ];
        }

        return $out;
    }

    public function bans(HttpResponse $response, HttpRequest $request): array {
        $userHash = $request->getHeaderLine('X-SharpChat-Signature');
        $realHash = hash_hmac('sha256', 'givemethebeans', $this->hashKey);

        if(!hash_equals($realHash, $userHash))
            return [];

        $warnings = UserWarning::byActive();
        $bans = [];

        foreach($warnings as $warning) {
            if(!$warning->isBan() || $warning->hasExpired())
                continue;

            $isPermanent = $warning->isPermanent();
            $bans[] = [
                'id' => $warning->getUser()->getId(),
                'expires' => date('c', $isPermanent ? 0x7FFFFFFF : $warning->getExpirationTime()),
                'is_permanent' => $isPermanent,
                'ip' => $warning->getUserRemoteAddress(),
                'username' => $warning->getUser()->getUsername(),
            ];
        }

        return $bans;
    }

    public function checkBan(HttpResponse $response, HttpRequest $request): array {
        $userHash = $request->getHeaderLine('X-SharpChat-Signature');
        $ipAddress = (string)$request->getQueryParam('a', FILTER_SANITIZE_STRING);
        $userId = (int)$request->getQueryParam('u', FILTER_SANITIZE_NUMBER_INT);

        $realHash = hash_hmac('sha256', "check#{$ipAddress}#{$userId}", $this->hashKey);
        if(!hash_equals($realHash, $userHash))
            return [];

        $response = [];
        $warning = UserWarning::byRemoteAddressActive($ipAddress)
            ?? UserWarning::byUserIdActive($userId);

        if($warning !== null) {
            $response['warning'] = $warning->getId();
            $response['id'] = $warning->getUserId();
            $response['ip'] = $warning->getUserRemoteAddress();
            $response['is_permanent'] = $warning->isPermanent();
            $response['expires'] = date('c', $response['is_permanent'] ? 0x7FFFFFFF : $warning->getExpirationTime());
        } else {
            $response['expires'] = date('c', 0);
            $response['is_permanent'] = false;
        }

        return $response;
    }

    public function createBan(HttpResponse $response, HttpRequest $request): int {
        $userHash = $request->getHeaderLine('X-SharpChat-Signature');
        $userId = (int)$request->getBodyParam('u', FILTER_SANITIZE_NUMBER_INT);
        $modId = (int)$request->getBodyParam('m', FILTER_SANITIZE_NUMBER_INT);
        $duration = (int)$request->getBodyParam('d', FILTER_SANITIZE_NUMBER_INT);
        $isPermanent = (int)$request->getBodyParam('p', FILTER_SANITIZE_NUMBER_INT);
        $reason = (string)$request->getBodyParam('r', FILTER_SANITIZE_STRING);

        $realHash = hash_hmac('sha256', "create#{$userId}#{$modId}#{$duration}#{$isPermanent}#{$reason}", $this->hashKey);
        if(!hash_equals($realHash, $userHash))
            return 403;

        if(empty($reason))
            $reason = 'Banned through chat.';

        if($isPermanent)
            $duration = -1;
        elseif($duration < 1)
            return 400;

        try {
            $userInfo = User::byId($userId);
        } catch(UserNotFoundException $ex) {
            return 404;
        }

        try {
            $modInfo = User::byId($modId);
        } catch(UserNotFoundException $ex) {
            return 404;
        }

        try {
            UserWarning::create(
                $userInfo,
                $modInfo,
                UserWarning::TYPE_BAHN,
                $duration,
                $reason
            );
        } catch(UserWarningCreationFailedException $ex) {
            return 500;
        }

        return 201;
    }

    public function removeBan(HttpResponse $response, HttpRequest $request): int {
        $userHash = $request->getHeaderLine('X-SharpChat-Signature');
        $type = (string)$request->getQueryParam('t', FILTER_SANITIZE_STRING);
        $subject = (string)$request->getQueryParam('s', FILTER_SANITIZE_STRING);

        $realHash = hash_hmac('sha256', "remove#{$type}#{$subject}", $this->hashKey);
        if(!hash_equals($realHash, $userHash))
            return 403;

        $warning = null;
        switch($type) {
            case 'ip':
                $warning = UserWarning::byRemoteAddressActive($subject);
                break;

            case 'user':
                try {
                    $userInfo = User::byUsername($subject);
                } catch(UserNotFoundException $ex) {
                    return 404;
                }

                $warning = UserWarning::byUserIdActive($userInfo->getId());
                break;
        }

        if($warning === null)
            return 404;

        $warning->delete();

        return 204;
    }

    public function login(HttpResponse $response, HttpRequest $request) {
        $currentUser = User::getCurrent();

        if($currentUser === null) {
            $response->redirect(url('auth-login'));
            return;
        }

        $params = $request->getQueryParams();

        try {
            $token = UserChatToken::create($currentUser);
        } catch(UserChatTokenCreationFailedException $ex) {
            return 500;
        }

        if(MSZ_DEBUG && isset($params['dump'])) {
            $ipAddr = $request->getRemoteAddress();
            $hash = hash_hmac('sha256', implode('#', [$token->getUserId(), $token->getToken(), $ipAddr]), $this->hashKey);

            $response->setText(sprintf(
                '/_sockchat.php?user_id=%d&token=%s&ip=%s&hash=%s',
                $token->getUserId(),
                $token->getToken(),
                urlencode($ipAddr),
                $hash
            ));
            return;
        }

        $cookieName = Config::get('sockChat.cookie', Config::TYPE_STR, 'sockchat_auth');
        $cookieData = implode('_', [$token->getUserId(), $token->getToken()]);
        $cookieDomain = '.' . $request->getHeaderLine('Host');
        setcookie($cookieName, $cookieData, $token->getExpirationTime(), '/', $cookieDomain);

        $configKey = isset($params['legacy']) ? 'sockChat.chatPath.legacy' : 'sockChat.chatPath.normal';
        $chatPath = Config::get($configKey, Config::TYPE_STR, '/');

        if(MSZ_DEBUG) {
            $response->setText(sprintf('Umi.Cookies.Set(\'%s\', \'%s\');', $cookieName, $cookieData));
        } else {
            $response->redirect($chatPath);
        }
    }

    public function bump(HttpResponse $response, HttpRequest $request): void {
        $userHash = $request->getHeaderLine('X-SharpChat-Signature');
        $bumpString = (string)$request->getBody();
        $realHash = hash_hmac('sha256', $bumpString, $this->hashKey);

        if(!hash_equals($realHash, $userHash))
            return;

        $bumpInfo = json_decode($bumpString);

        if(empty($bumpInfo))
            return;

        foreach($bumpInfo as $bumpUser)
            try {
                User::byId($bumpUser->id)->bumpActivity($bumpUser->ip);
            } catch(UserNotFoundException $ex) {}
    }

    public function verify(HttpResponse $response, HttpRequest $request): array {
        $userHash = $request->getHeaderLine('X-SharpChat-Signature');

        if(strlen($userHash) !== 64)
            return ['success' => false, 'reason' => 'length'];

        $authInfo = json_decode((string)$request->getBody());

        if(!isset($authInfo->user_id, $authInfo->token, $authInfo->ip))
            return ['success' => false, 'reason' => 'data'];

        $realHash = hash_hmac('sha256', implode('#', [$authInfo->user_id, $authInfo->token, $authInfo->ip]), $this->hashKey);

        if(!hash_equals($realHash, $userHash))
            return ['success' => false, 'reason' => 'hash'];

        try {
            $userInfo = User::byId($authInfo->user_id);
        } catch(UserNotFoundException $ex) {
            return ['success' => false, 'reason' => 'user'];
        }

        $authMethod = mb_substr($authInfo->token, 0, 5);

        if($authMethod === 'PASS:') {
            //if(time() > 1577750400)
                return ['success' => false, 'reason' => 'unsupported'];

            //if(user_password_verify_db($authInfo->user_id, mb_substr($authInfo->token, 5)))
            //    $userId = $authInfo->user_id;
        } elseif($authMethod === 'SESS:') {
            $sessionToken = mb_substr($authInfo->token, 5);

            $authToken = AuthToken::unpack($sessionToken);
            if($authToken->isValid())
                $sessionToken = $authToken->getSessionToken();

            try {
                $sessionInfo = UserSession::byToken($sessionToken);
            } catch(UserSessionNotFoundException $ex) {
                return ['success' => false, 'reason' => 'token'];
            }

            if($sessionInfo->getUserId() !== $userInfo->getId())
                return ['success' => false, 'reason' => 'user'];

            if($sessionInfo->hasExpired()) {
                $sessionInfo->delete();
                return ['success' => false, 'reason' => 'expired'];
            }

            $sessionInfo->bump();
        } else {
            try {
                $token = UserChatToken::byExact($userInfo, $authInfo->token);
            } catch(UserChatTokenNotFoundException $ex) {
                return ['success' => false, 'reason' => 'token'];
            }

            if($token->hasExpired()) {
                $token->delete();
                return ['success' => false, 'reason' => 'expired'];
            }
        }

        $userInfo->bumpActivity($authInfo->ip);

        $perms = self::PERMS_DEFAULT;

        if(perms_check_user(MSZ_PERMS_USER, $userInfo->getId(), MSZ_PERM_USER_MANAGE_USERS))
            $perms |= self::PERMS_MANAGE_USERS;
        if(perms_check_user(MSZ_PERMS_USER, $userInfo->getId(), MSZ_PERM_USER_MANAGE_WARNINGS))
            $perms |= self::PERMS_MANAGE_WARNS;
        if(perms_check_user(MSZ_PERMS_USER, $userInfo->getId(), MSZ_PERM_USER_CHANGE_BACKGROUND))
            $perms |= self::PERMS_CHANGE_BACKG;
        if(perms_check_user(MSZ_PERMS_FORUM, $userInfo->getId(), MSZ_PERM_FORUM_MANAGE_FORUMS))
            $perms |= self::PERMS_MANAGE_FORUM;

        return [
            'success' => true,
            'user_id' => $userInfo->getId(),
            'username' => $userInfo->getUsername(),
            'colour_raw' => $userInfo->getColour()->getRaw(),
            'hierarchy' => $userInfo->getRank(),
            'is_silenced' => date('c', $userInfo->isSilenced() || $userInfo->isBanned() ? ($userInfo->isActiveWarningPermanent() ? strtotime('10 years') : $userInfo->getActiveWarningExpiration()) : 0),
            'perms' => $perms,
        ];
    }
}
