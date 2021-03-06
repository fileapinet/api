<?php

namespace FileApi\ApiBundle\Model;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\HeaderBag;

class HttpRequest extends SymfonyRequest
{
    private $symfonyRequest;

    public function __construct(SymfonyRequest $symfonyRequest)
    {
        $this->symfonyRequest = $symfonyRequest;
    }

    public function getUri()
    {
        return $this->symfonyRequest->getUri();
    }

    /**
     * Get query string parameters.
     */
    public function getQueryStringParams()
    {
        return $this->symfonyRequest->query;
    }

    /**
     * @param  string  $param
     * @return boolean
     */
    public function hasQueryStringParam($param)
    {
        return $this->symfonyRequest->query->has($param);
    }

    /**
     * Get POST or PUT parameters.
     */
    public function getBodyParams()
    {
        return $this->symfonyRequest->request;
    }

    /**
     * @param  string  $param
     * @return boolean
     */
    public function hasBodyParam($param)
    {
        return $this->symfonyRequest->request->has($param);
    }

    /**
     * Get file upload parameters.
     */
    public function getFileParams()
    {
        return $this->symfonyRequest->files;
    }

    /**
     * @param  string  $param
     * @return boolean
     */
    public function hasFileParam($param)
    {
        return $this->symfonyRequest->files->has($param);
    }

    /**
     * @return HeaderBag
     */
    public function getHeaders()
    {
        return $this->symfonyRequest->headers;
    }

    /**
     * @return HeaderBag
     */
    public function getHeadersCustomToUs()
    {
        $headersCustomToUs = [];

        foreach ($this->symfonyRequest->headers->all() as $key => $value) {
            if (strpos(strtolower($key), 'x-fileapi-') === 0) {
                $headersCustomToUs[$key] = $value;
            }
        }

        return new HeaderBag($headersCustomToUs);
    }
}
