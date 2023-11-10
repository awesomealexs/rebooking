<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiAuthenticator extends AbstractAuthenticator
{

    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        //if (!$this->checkAuth($request)) {
        //    return $this->json([
        //        'success' => false,
        //        'message' => 'auth failed'
        //    ], 401);
        //}

        $secret = getenv('API_AUTH_KEY');
        $time = time();


        $signature = $request->headers->get('signature');

        $timestamp = $request->headers->get('timestamp');

        if (null === $signature) {
            throw new CustomUserMessageAuthenticationException('No signature provided');
        }

        if (null === $timestamp) {
            throw new CustomUserMessageAuthenticationException('No timestamp provided');
        }
        if ((time() - (int)$timestamp) > 5) {
            throw new CustomUserMessageAuthenticationException('Timestamp too old provided');
        }

        if (md5(getenv('API_AUTH_KEY') . $timestamp) !== $signature) {
            throw new CustomUserMessageAuthenticationException('Wrong signature provided');
        }

        return new SelfValidatingPassport(new UserBadge($signature, fn() => new User()));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'success' => false,
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
            'headers' => $request->headers->all(),
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
