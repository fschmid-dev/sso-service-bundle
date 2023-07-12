<?php

namespace FSchmidDev\SSOServiceBundle\SSO;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use FSchmidDev\SSOServiceBundle\SSO\Event\UserCreatedEvent;
use FSchmidDev\SSOServiceBundle\SSO\Event\UserEvent;
use FSchmidDev\SSOServiceBundle\SSO\Event\UserUpdatedEvent;
use InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class UserData
{
    public function __construct(
        private readonly EventDispatcherInterface  $eventDispatcher,
        private readonly EntityManagerInterface    $entityManager,
        private readonly HttpClientInterface       $httpClient,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly SerializerInterface       $serializer,
        private readonly string                    $requestUrl,
        private readonly string                    $userClass
    )
    {
    }

    public function fetchAndMerge(object|null $user, string $token)
    {
        if (!$user instanceof $this->userClass && $user !== null) {
            throw new InvalidArgumentException(
                sprintf(
                    'User object is invalid! Must be of type "%s", "%s" given!',
                    $this->userClass,
                    $user::class
                )
            );
        }

        $response = $this->httpClient->request(
            'GET',
            $this->requestUrl . '/user-profile',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ]
            ]
        );

        $data = $response->getContent();
        if ($user) {
            $userData = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            foreach ($userData as $field => $value) {
                try {
                    if (in_array($field, ['createdAt', 'updatedAt'], true)) {
                        $value = new DateTimeImmutable($value);
                    }

                    $this->propertyAccessor->setValue($user, $field, $value);
                } catch (NoSuchPropertyException $e) {
                    // We try to set a property, that doesn't exist in the user class
                    //  Maybe log in a custom sso_service.log?
                } catch (Throwable $e) {
                    // TODO: log? display error?
                }
            }
            $eventClass = UserUpdatedEvent::class;
        } else {
            $user = $this->serializer->deserialize($data, $this->userClass, 'json');
            $eventClass = UserCreatedEvent::class;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $event = new $eventClass($user);
        $event = $this->eventDispatcher->dispatch($event, UserEvent::NAME);

        return $event->getUser();
    }
}
