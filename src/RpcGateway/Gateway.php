<?php

namespace RpcGateway;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Gateway
{
    /**
     * @var SymfonyResponse
     */
    protected $response;

    /**
     * @var SymfonyRequest
     */
    protected $request;

    /**
     * @var string
     */
    protected $serviceClassNamespace = '\\App\\Rpc\\Service\\';


    function __construct(SymfonyRequest $request)
    {
        $this->setRequest($request);
        $this->setResponse(new SymfonyResponse());
    }

    /**
     * @return SymfonyResponse
     */
    public function dispatch()
    {
        try {
            /** @var $jsonRequest array */
            $jsonRequest = $this->getJsonRequestFromRequestRawBody(
                $this->getRequest()
            );

            /** @var $rpcRequest Request */
            $rpcRequest = $this->getRpcRequestFromJsonRequest(
                $jsonRequest, $this->getRequest()
            );

            $result = $this->invokeRpcRequest($rpcRequest);

        } catch (\Exception $exception) {

            $result = array(
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
            );

            return $this->putErrorAsJsonRpcIntoResponse(
                $result,
                $this->getResponse()
            );
        }

        return $this->putResultAsJsonRpcIntoResponse(
            $result,
            $this->getResponse()
        );
    }

    /**
     * @param SymfonyRequest $request
     * @return array
     * @throws \Exception
     */
    protected function getJsonRequestFromRequestRawBody(SymfonyRequest $request)
    {
        $requestRawBody = $request->getContent();
        $rpcData = json_decode($requestRawBody, true);

        if (!is_array($rpcData)) {
            throw new \Exception("Invalid request at " . __METHOD__);
        }

        return $rpcData;
    }

    /**
     * @return SymfonyRequest
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param SymfonyRequest $request
     */
    public function setRequest($request)
    {
        $this->request = $request;
    }

    /**
     * @param $jsonData array
     * @param $request SymfonyRequest
     * @throws \Exception
     * @return \RpcGateway\Request
     */
    protected function getRpcRequestFromJsonRequest($jsonData, $request)
    {
        $rpcRequest = new Request();

        if (!array_key_exists('method', $jsonData)) {
            throw new \Exception("Invalid method at " . __METHOD__);
        }

        if (!(array_key_exists('params', $jsonData) && is_array($jsonData['params']))) {
            throw new \Exception("Invalid params at " . __METHOD__);
        }

        $omitToken = (bool)$jsonData['omitToken'];

        if(!$omitToken) {
            $token = $jsonData['token'];

            if (empty($token)) {
                throw new \Exception("No token at " . __METHOD__);
            }

            $rpcRequest->setToken((string)$token);
        }

        $rpcRequest->setMethod((string)$jsonData['method']);
        $rpcRequest->setParams((array)$jsonData['params']);

        return $rpcRequest;
    }

    /**
     * @param $rpcRequest \RpcGateway\Request
     * @return mixed
     * @throws \Exception
     */
    protected function invokeRpcRequest($rpcRequest)
    {
        $this->throwErrorIfRpcRequestIsInvalid($rpcRequest);

        // create a PHP compatible version of the requested service class
        $serviceClassName = $this->getServiceClassNamespace() . str_replace(Request::METHOD_DELIMITER, '_',
                $rpcRequest->getClassName());
        $serviceClass = new $serviceClassName($rpcRequest->getToken());
        $serviceClassReflection = new \ReflectionClass($serviceClassName);
        $serviceMethodReflection = $serviceClassReflection->getMethod($rpcRequest->getMethodName());
        $serviceCallResult = $serviceMethodReflection->invokeArgs($serviceClass, $rpcRequest->getParams());

        return $serviceCallResult;
    }

    /**
     * @param $rpcRequest \RpcGateway\Request
     * @throws \Exception
     * @return void
     */
    protected function throwErrorIfRpcRequestIsInvalid($rpcRequest)
    {
        $serviceMethodName = $rpcRequest->getMethodName();

        // create a PHP compatible version of the requested service class
        $serviceClassName =
            $this->getServiceClassNamespace()
            . str_replace(Request::METHOD_DELIMITER, '_', $rpcRequest->getClassName());

        if (!class_exists($serviceClassName)) {
            throw new \Exception("Invalid rpc.method [" . $rpcRequest->getMethod() . "] (not found) at " . __METHOD__);
        }

        $serviceClassReflection = new \ReflectionClass($serviceClassName);

        if (($serviceClassReflection->isAbstract())
            || ($serviceClassReflection->isInterface())
            || ($serviceClassReflection->isInternal())
        ) {
            throw new \Exception("Invalid rpc.class [" . $rpcRequest->getMethod() . "] at " . __METHOD__);
        }

        if ($serviceClassReflection->hasMethod($serviceMethodName) !== true) {
            throw new \Exception("Invalid rpc.method [" . $rpcRequest->getMethod() . "] (not found) at " . __METHOD__);
        }

        $serviceMethodReflection = $serviceClassReflection->getMethod($serviceMethodName);

        if (
            ($serviceMethodReflection->isAbstract())
            || ($serviceMethodReflection->isConstructor())
            || ($serviceMethodReflection->isStatic())
            || (!$serviceMethodReflection->isPublic())
        ) {
            throw new \Exception("Invalid rpc.method [" . $rpcRequest->getMethod() . "] (not invokable) at " . __METHOD__);
        }

        $argsExpectedMin = $serviceMethodReflection->getNumberOfRequiredParameters();
        $argsExpectedMax = $serviceMethodReflection->getNumberOfParameters();
        $argsOptional = $argsExpectedMax - $argsExpectedMin;
        $argsGiven = count($rpcRequest->getParams());

        if (($argsGiven < $argsExpectedMin) || ($argsGiven > $argsExpectedMax)) {
            throw new \Exception(
                'Invalid rpc.method ['
                . $rpcRequest->getMethod()
                . ']'
                . " Wrong number of parameters:"
                . " " . $argsGiven . " given"
                . " (required: " . $argsExpectedMin
                . " optional: " . $argsOptional . ")"
                . " expectedMin: " . $argsExpectedMin
                . " expectedMax: " . $argsExpectedMax
            );
        }
    }

    /**
     * @return string
     */
    public function getServiceClassNamespace()
    {
        return $this->serviceClassNamespace;
    }

    /**
     * @param string $serviceClassNamespace
     */
    public function setServiceClassNamespace($serviceClassNamespace)
    {
        $this->serviceClassNamespace = $serviceClassNamespace;
    }

    /**
     * @param $result array
     * @param $response SymfonyResponse
     */
    protected function putErrorAsJsonRpcIntoResponse($result, $response)
    {
        $response->headers->set(
            "Content-Type",
            "application/json; charset=utf-8",
            true // replace header if we must!
        );

        $responseData = array(
            'error' => $result,
            'result' => null
        );

        $response->setContent(
            json_encode($responseData)
        );

        return $response;
    }

    /**
     * @return SymfonyResponse
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param SymfonyResponse $response
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }

    /**
     * @param $result array
     * @param $response SymfonyResponse
     */
    protected function putResultAsJsonRpcIntoResponse($result, $response)
    {
        $response->headers->set(
            "Content-Type",
            "application/json; charset=utf-8",
            true // replace header if we must!
        );

        $responseData = array(
            'error' => null,
            'result' => $result
        );

        $response->setContent(
            json_encode($responseData)
        );

        return $response;
    }
}