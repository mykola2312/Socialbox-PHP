<?php

namespace Socialbox\Classes\StandardMethods;

use Socialbox\Abstracts\Method;
use Socialbox\Enums\Flags\PeerFlags;
use Socialbox\Enums\Flags\SessionFlags;
use Socialbox\Enums\StandardError;
use Socialbox\Exceptions\DatabaseOperationException;
use Socialbox\Exceptions\StandardException;
use Socialbox\Interfaces\SerializableInterface;
use Socialbox\Managers\CaptchaManager;
use Socialbox\Managers\RegisteredPeerManager;
use Socialbox\Managers\SessionManager;
use Socialbox\Objects\ClientRequest;
use Socialbox\Objects\ClientRequestOld;
use Socialbox\Objects\RpcRequest;

class VerificationAnswerImageCaptcha extends Method
{

    /**
     * @inheritDoc
     */
    public static function execute(ClientRequest $request, RpcRequest $rpcRequest): ?SerializableInterface
    {
        if(!$rpcRequest->containsParameter('answer'))
        {
            return $rpcRequest->produceError(StandardError::RPC_INVALID_ARGUMENTS, 'The answer parameter is required');
        }

        $session = $request->getSession();

        try
        {
            if(CaptchaManager::getCaptcha($session->getPeerUuid())->isExpired())
            {
                return $rpcRequest->produceError(StandardError::CAPTCHA_EXPIRED, 'The captcha has expired');
            }
        }
        catch(DatabaseOperationException $e)
        {
            throw new StandardException("There was an unexpected error while trying to get the captcha", StandardError::INTERNAL_SERVER_ERROR, $e);
        }

        try
        {
            $result = CaptchaManager::answerCaptcha($session->getPeerUuid(), $rpcRequest->getParameter('answer'));

            if($result)
            {
                SessionManager::removeFlags($request->getSessionUuid(), [SessionFlags::VER_IMAGE_CAPTCHA]);
            }
        }
        catch (DatabaseOperationException $e)
        {
            throw new StandardException("There was an unexpected error while trying to answer the captcha", StandardError::INTERNAL_SERVER_ERROR, $e);
        }

        // Check if all registration flags are removed
        if(SessionFlags::isComplete($request->getSession()->getFlags()))
        {
            // Set the session as authenticated
            try
            {
                SessionManager::setAuthenticated($request->getSessionUuid(), true);
                SessionManager::removeFlags($request->getSessionUuid(), [SessionFlags::REGISTRATION_REQUIRED, SessionFlags::AUTHENTICATION_REQUIRED]);
            }
            catch (DatabaseOperationException $e)
            {
                return $rpcRequest->produceError(StandardError::INTERNAL_SERVER_ERROR, $e);
            }
        }

        return $rpcRequest->produceResponse($result);
    }
}