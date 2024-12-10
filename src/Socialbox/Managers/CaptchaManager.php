<?php

namespace Socialbox\Managers;

use DateTime;
use PDOException;
use Socialbox\Classes\Database;
use Socialbox\Classes\Logger;
use Socialbox\Classes\Utilities;
use Socialbox\Enums\Status\CaptchaStatus;
use Socialbox\Exceptions\DatabaseOperationException;
use Socialbox\Objects\Database\CaptchaRecord;
use Socialbox\Objects\Database\RegisteredPeerRecord;

class CaptchaManager
{
    /**
     * Creates a new captcha for the given peer UUID.
     *
     * @param string|RegisteredPeerRecord $peer_uuid The UUID of the peer to create the captcha for.
     * @return string The answer to the captcha.
     * @throws DatabaseOperationException If the operation fails.
     */
    public static function createCaptcha(string|RegisteredPeerRecord $peer_uuid): string
    {
        // If the peer_uuid is a RegisteredPeerRecord, get the UUID
        if($peer_uuid instanceof RegisteredPeerRecord)
        {
            $peer_uuid = $peer_uuid->getUuid();
        }

        $answer = Utilities::randomString(6, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
        $current_time = (new DateTime())->setTimestamp(time());

        if(!self::captchaExists($peer_uuid))
        {
            Logger::getLogger()->debug('Creating a new captcha record for peer ' . $peer_uuid);
            $statement = Database::getConnection()->prepare("INSERT INTO captcha_images (peer_uuid, created, answer) VALUES (?, ?, ?)");
            $statement->bindParam(1, $peer_uuid);
            $statement->bindParam(2, $current_time);
            $statement->bindParam(3, $answer);

            try
            {
                $statement->execute();
            }
            catch(PDOException $e)
            {
                throw new DatabaseOperationException('Failed to create a captcha in the database', $e);
            }

            return $answer;
        }

        Logger::getLogger()->debug('Updating an existing captcha record for peer ' . $peer_uuid);
        $statement = Database::getConnection()->prepare("UPDATE captcha_images SET answer=?, status='UNSOLVED', created=? WHERE peer_uuid=?");
        $statement->bindParam(1, $answer);
        $statement->bindParam(2, $current_time);
        $statement->bindParam(3, $peer_uuid);

        try
        {
            $statement->execute();
        }
        catch(PDOException $e)
        {
            throw new DatabaseOperationException('Failed to update a captcha in the database', $e);
        }

        return $answer;
    }

    /**
     * Answers a captcha for the given peer UUID.
     *
     * @param string|RegisteredPeerRecord $peer_uuid The UUID of the peer to answer the captcha for.
     * @param string $answer The answer to the captcha.
     * @return bool True if the answer is correct, false otherwise.
     * @throws DatabaseOperationException If the operation fails.
     */
    public static function answerCaptcha(string|RegisteredPeerRecord $peer_uuid, string $answer): bool
    {
        if($peer_uuid instanceof RegisteredPeerRecord)
        {
            $peer_uuid = $peer_uuid->getUuid();
        }

        // Return false if the captcha does not exist
        if(!self::captchaExists($peer_uuid))
        {
            Logger::getLogger()->warning(sprintf("The requested captcha does not exist for the peer %s", $peer_uuid));
            return false;
        }

        $captcha = self::getCaptcha($peer_uuid);

        // Return false if the captcha has already been solved
        if($captcha->getStatus() === CaptchaStatus::SOLVED)
        {
            Logger::getLogger()->warning(sprintf("The requested captcha has already been solved for the peer %s", $peer_uuid));
            return false;
        }

        // Return false if the captcha is older than 5 minutes
        if ($captcha->isExpired())
        {
            Logger::getLogger()->warning(sprintf("The requested captcha is older than 5 minutes for the peer %s", $peer_uuid));
            return false;
        }

        // Verify the answer
        if($captcha->getAnswer() !== $answer)
        {
            Logger::getLogger()->warning(sprintf("The answer to the requested captcha is incorrect for the peer %s", $peer_uuid));
            return false;
        }

        Logger::getLogger()->debug(sprintf("The answer to the requested captcha is correct for the peer %s", $peer_uuid));
        $statement = Database::getConnection()->prepare("UPDATE captcha_images SET status='SOLVED', answered=NOW() WHERE peer_uuid=?");
        $statement->bindParam(1, $peer_uuid);

        try
        {
            $statement->execute();
        }
        catch(PDOException $e)
        {
            throw new DatabaseOperationException('Failed to update a captcha in the database', $e);
        }

        return true;
    }

    /**
     * Retrieves the captcha record for the given peer UUID.
     *
     * @param string|RegisteredPeerRecord $peer_uuid The UUID of the peer to retrieve the captcha for.
     * @return CaptchaRecord The captcha record.
     * @throws DatabaseOperationException If the operation fails.
     */
    public static function getCaptcha(string|RegisteredPeerRecord $peer_uuid): CaptchaRecord
    {
        // If the peer_uuid is a RegisteredPeerRecord, get the UUID
        if($peer_uuid instanceof RegisteredPeerRecord)
        {
            $peer_uuid = $peer_uuid->getUuid();
        }

        Logger::getLogger()->debug('Getting the captcha record for peer ' . $peer_uuid);

        try
        {
            $statement = Database::getConnection()->prepare("SELECT * FROM captcha_images WHERE peer_uuid=? LIMIT 1");
            $statement->bindParam(1, $peer_uuid);
            $statement->execute();
            $result = $statement->fetch();
        }
        catch(PDOException $e)
        {
            throw new DatabaseOperationException('Failed to get a captcha from the database', $e);
        }

        if($result === false)
        {
            throw new DatabaseOperationException('The requested captcha does not exist');
        }

        return CaptchaRecord::fromArray($result);
    }

    /**
     * Checks if a captcha exists for the given peer UUID.
     *
     * @param string|RegisteredPeerRecord $peer_uuid The UUID of the peer to check for a captcha.
     * @return bool True if a captcha exists, false otherwise.
     * @throws DatabaseOperationException If the operation fails.
     */
    private static function captchaExists(string|RegisteredPeerRecord $peer_uuid): bool
    {
        // If the peer_uuid is a RegisteredPeerRecord, get the UUID
        if($peer_uuid instanceof RegisteredPeerRecord)
        {
            $peer_uuid = $peer_uuid->getUuid();
        }

        Logger::getLogger()->debug('Checking if a captcha exists for peer ' . $peer_uuid);

        try
        {
            $statement = Database::getConnection()->prepare("SELECT COUNT(*) FROM captcha_images WHERE peer_uuid=?");
            $statement->bindParam(1, $peer_uuid);
            $statement->execute();
            $result = $statement->fetchColumn();
        }
        catch(PDOException $e)
        {
            throw new DatabaseOperationException('Failed to check if a captcha exists in the database', $e);
        }

        return $result > 0;
    }
}