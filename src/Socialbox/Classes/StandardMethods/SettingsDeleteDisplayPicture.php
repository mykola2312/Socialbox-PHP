<?php

    namespace Socialbox\Classes\StandardMethods;

    use Exception;
    use Socialbox\Abstracts\Method;
    use Socialbox\Classes\Configuration;
    use Socialbox\Enums\StandardError;
    use Socialbox\Exceptions\StandardException;
    use Socialbox\Interfaces\SerializableInterface;
    use Socialbox\Managers\RegisteredPeerManager;
    use Socialbox\Objects\ClientRequest;
    use Socialbox\Objects\RpcRequest;

    class SettingsDeleteDisplayPicture extends Method
    {
        /**
         * @inheritDoc
         */
        public static function execute(ClientRequest $request, RpcRequest $rpcRequest): ?SerializableInterface
        {
            if(Configuration::getRegistrationConfiguration()->isDisplayPictureRequired())
            {
                return $rpcRequest->produceError(StandardError::FORBIDDEN, 'A display picture is required for this server');
            }

            try
            {
                // Set the password
                RegisteredPeerManager::deleteDisplayPicture($request->getPeer());
            }
            catch(Exception $e)
            {
                throw new StandardException('Failed to update display picture: ' . $e->getMessage(), StandardError::INTERNAL_SERVER_ERROR, $e);
            }

            return $rpcRequest->produceResponse(true);
        }
    }