<?php

namespace FileApi\ApiBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use JsonSerializable;
use Symfony\Component\HttpFoundation\Request;

/**
 * @MongoDB\Document
 */
class Order implements JsonSerializable
{
    /**
     * @MongoDB\Id
     */
    private $id;

    /**
     * @MongoDB\Date
     */
    private $date;

    /**
     * @MongoDB\String
     */
    private $requestUrl;

    /**
     * @MongoDB\String
     */
    private $fileSystemPath;

    /**
     * @MongoDB\String
     */
    private $fileSystemUrl;

    /**
     * @MongoDB\Hash
     */
    private $result;

    public function __construct(Request $request, $fileSystemPath, $fileSystemUrl)
    {
        $this->requestUrl = $request->getUri();
        $this->fileSystemPath = $fileSystemPath;
        $this->fileSystemUrl = $fileSystemUrl;
        $this->date = new \DateTime();
        $this->result = [];
    }

    public function getId()
    {
        return $this->id;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function getFileSystemPath()
    {
        return $this->fileSystemPath;
    }

    public function getFileSystemUrl()
    {
        return $this->fileSystemUrl;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function setResult($result)
    {
        $this->result = $result;
    }

    public function addResultAttribute($key, $value)
    {
        $this->result[$key] = $value;
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'date' => date_format($this->date, DATE_ISO8601),
            'requestUrl' => $this->requestUrl,
            'fileSystemPath' => $this->fileSystemPath,
            'fileSystemUrl' => $this->fileSystemUrl,
            'result' => $this->result,
        ];
    }
}
