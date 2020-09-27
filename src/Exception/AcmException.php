<?php
namespace Dleno\AliYunAcm\Exception;

class AcmException extends \RuntimeException
{
    /**
     * @var string
     */
    private $requestId;

    /**
     * Exception constructor
     *
     * @param string $code
     *            log service error code.
     * @param string $message
     *            detailed information for the exception.
     * @param string $requestId
     *            the request id of the response, '' is set if client error.
     */
    public function __construct($message, $requestId = '')
    {
        parent::__construct($message);
        $this->message = $message;
        $this->requestId = $requestId;
    }

    /**
     * The __toString() method allows a class to decide how it will react when
     * it is treated like a string.
     *
     * @return string
     */
    public function __toString() {
        return "AcmException: \n{\n    ErrorMessage: $this->message\n    RequestId: $this->requestId\n}\n";
    }

    /**
     * Get AcmException error message.
     *
     * @return string
     */
    public function getErrorMessage() {
        return $this->message;
    }

    /**
     * Get log service sever requestid, '' is set if client or Http error.
     *
     * @return string
     */
    public function getRequestId() {
        return $this->requestId;
    }
}