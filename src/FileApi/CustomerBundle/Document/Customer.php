<?php

namespace FileApi\CustomerBundle\Document;

use AmyBoyd\HistoryBundle\Document\HasHistoryTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document
 */
class Customer
{
    use HasHistoryTrait;

    /**
     * @MongoDB\Id
     */
    private $id;

    /**
     * @MongoDB\String
     */
    private $name;

    /**
     * @MongoDB\String
     */
    private $contactEmail;

    /**
     * @MongoDB\String
     */
    private $billingEmail;

    /**
     * @MongoDB\String
     */
    private $apiKey;

    /**
     * @MongoDB\Date
     */
    private $createdAt;

    public function __construct($name, $contactEmail, $billingEmail)
    {
        $this->name = $name;
        $this->contactEmail = $contactEmail;
        $this->billingEmail = $billingEmail;
        $this->apiKey = $this->generateString(20);
        $this->createdAt = new \DateTime();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getContactEmail()
    {
        return $this->contactEmail;
    }

    public function getBillingEmail()
    {
        return $this->billingEmail;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Generates a random string of length depending on the input $length.
     *
     * @param  int $length
     * @return string
     */
    private function generateString($length)
    {
        if ($length % 2 !== 0) {
            throw new \InvalidArgumentException('Length must be an even number, got: ' . $length);
        }

        $generator = new SecureRandom();

        return bin2hex($generator->nextBytes($length / 2));
    }
}
