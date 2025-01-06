<?php

    namespace Socialbox\Classes\StandardMethods;

    use Socialbox\Abstracts\Method;
    use Socialbox\Enums\Flags\SessionFlags;
    use Socialbox\Enums\StandardError;
    use Socialbox\Exceptions\DatabaseOperationException;
    use Socialbox\Interfaces\SerializableInterface;
    use Socialbox\Managers\SessionManager;
    use Socialbox\Objects\ClientRequest;
    use Socialbox\Objects\RpcRequest;

    class AcceptPrivacyPolicy extends Method
    {
        /**
         * Executes the process of accepting the privacy policy.
         *
         * @inheritDoc
         */
        public static function execute(ClientRequest $request, RpcRequest $rpcRequest): ?SerializableInterface
        {
            $session = $request->getSession();
            if(!$session->flagExists(SessionFlags::VER_PRIVACY_POLICY))
            {
                return $rpcRequest->produceError(StandardError::FORBIDDEN, 'Privacy policy has already been accepted');
            }

            try
            {
                // Check & update the session flow
                SessionManager::updateFlow($session, [SessionFlags::VER_PRIVACY_POLICY]);
            }
            catch (DatabaseOperationException $e)
            {
                return $rpcRequest->produceError(StandardError::INTERNAL_SERVER_ERROR, $e);
            }

            return $rpcRequest->produceResponse(true);
        }
    }