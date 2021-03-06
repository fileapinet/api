<?php

namespace FileApi\ApiBundle\Document;

use AmyBoyd\HistoryBundle\Document\HasHistoryTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use FileApi\ApiBundle\Model\HttpRequest;

/**
 * @MongoDB\Document
 */
class Order
{
    use HasHistoryTrait;

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
     *
     * The result, show to the customer in their API response.
     */
    private $result;

    /**
     * @MongoDB\Hash
     *
     * Any attributes to record internally, that are not shown to the customer.
     */
    private $internalAttributes;

    public function __construct(HttpRequest $request, $fileSystemPath, $fileSystemUrl)
    {
        $this->requestUrl = $request->getUri();
        $this->fileSystemPath = $fileSystemPath;
        $this->fileSystemUrl = $fileSystemUrl;
        $this->createdAt = new \DateTime();
        $this->result = [];
        $this->resultAttributeTimestamps = [];
        $this->internalAttributes = [];

        $this->input = [];

        $this->setRequest($request);
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

    public function addInternalAttribute($key, $value)
    {
        $value = utf8_encode(utf8_decode($value));

        $this->internalAttributes[$key] = $value;
    }

    private function setRequest(HttpRequest $request)
    {
        $this->input['requestUrl'] = $request->getUri();
        $this->input['requestQueryParams'] = $request->getQueryStringParams()->all();
        $this->input['requestBodyParams'] = $request->getBodyParams()->all();
        $this->input['requestHeaders'] = $request->getHeaders()->all();
        $this->input['requestHeadersCustomToUs'] = $request->getHeadersCustomToUs()->all();
    }
}
