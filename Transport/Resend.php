<?php

namespace Kanboard\Plugin\Resend\Transport;

use Kanboard\Core\Base;
use Kanboard\Core\Mail\ClientInterface;

/**
 * Mail transport that sends through the Resend HTTP API.
 */
class Resend extends Base implements ClientInterface
{
    public function sendEmail($recipientEmail, $recipientName, $subject, $html, $authorName, $authorEmail = '')
    {
    }
}
