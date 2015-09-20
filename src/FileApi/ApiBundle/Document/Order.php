<?php

namespace FileApi\ApiBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\HttpFoundation\Request;

/**
 * @MongoDB\Document
 */
class Order
{
    /**
     * @MongoDB\Id
     */
    private $id;

    /**
     * @MongoDB\Date
     *
     * The date the order was created.
     */
    private $createdAt;

    /**
     * @MongoDB\Hash
     *
     * The dates each result attribute was added (as \MongoDate objects, because @MongoDB\Hash does
     * not automatically convert \DateTime objects to \MongoDate).
     */
    private $resultAttributeTimestamps;

    /**
     * @MongoDB\Date
     *
     * The date the final result attribute was added. This allows us to easily calculate the time
     * each order takes to complete.
     */
    private $lastResultAttributeAddedAt;

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
    private $input;

    /**
     * @MongoDB\Hash
     */
    private $result;

    public function __construct(Request $request, $fileSystemPath, $fileSystemUrl)
    {
        $this->requestUrl = $request->getUri();
        $this->fileSystemPath = $fileSystemPath;
        $this->fileSystemUrl = $fileSystemUrl;
        $this->createdAt = new \DateTime();
        $this->result = [];
        $this->resultAttributeTimestamps = [];

        $this->input = [];
        $this->input['requestUrl'] = $request->getUri();
        $this->input['requestQueryParams'] = $request->query->all();
        $this->input['requestBodyParams'] = $request->request->all();
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

    public function getInput()
    {
        return $this->input;
    }

    public function getInputAttribute($attribute)
    {
        return $this->input[$attribute];
    }

    public function addInputAttribute($key, $value)
    {
        $this->input[$key] = $value;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getLastResultAttributeAddedAt()
    {
        return $this->lastResultAttributeAddedAt;
    }

    public function addResultAttribute($key, $value)
    {
        $this->result[$key] = $value;

        // See the comment for `resultAttributeTimestamps` for why this is a `\MongoDate` and not a
        // `\DateTime`.
        $this->resultAttributeTimestamps[$key] = new \MongoDate();

        $this->lastResultAttributeAddedAt = new \DateTime();
    }
}
