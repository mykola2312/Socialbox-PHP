<?php

    namespace Socialbox\Enums\Types;

    enum RequestType : string
    {
        /**
         * Represents the action of sending a ping request.
         */
        case PING = 'ping';

        /**
         * Represents the action of getting server information (Non-RPC Request)
         */
        case INFO = 'info';

        /**
         * Represents the action of initiating a session.
         */
        case INITIATE_SESSION = 'init';

        /**
         * Represents the action of performing a Diffie-Hellman key exchange.
         */
        case DHE_EXCHANGE = 'dhe';

        /**
         * Represents the action of performing a remote procedure call.
         */
        case RPC = 'rpc';
    }
