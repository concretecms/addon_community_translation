<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity;

use DateTimeImmutable;
use ReflectionClass;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Notificatons.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Notification",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationNotifications",
 *     options={"comment": "Notificatons"}
 * )
 */
class Notification
{
    /**
     * Notification ID.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned": true, "comment": "Notification ID"})
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\GeneratedValue(strategy="AUTO")
     */
    protected ?int $id;

    /**
     * Date/time of the record creation.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=false, options={"comment": "Date/time of the record creation"})
     */
    protected DateTimeImmutable $createdOn;

    /**
     * Date/time when the data was last modified.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=false, options={"comment": "Date/time when the data was last modified"})
     */
    protected DateTimeImmutable $updatedOn;

    /**
     * Fully qualified name of the category class.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment": "Fully qualified name of the category class"})
     */
    protected string $fqnClass;

    /**
     * The notification priority (bigger values for higher priorities).
     *
     * @Doctrine\ORM\Mapping\Column(type="smallint", nullable=false, options={"unsigned": false, "default" : 0, "comment": "Notification priority (bigger values for higher priorities)"})
     */
    protected int $priority;

    /**
     * Data specific to the notification class.
     *
     * @Doctrine\ORM\Mapping\Column(type="array", nullable=false, options={"comment": "Data specific to the notification class"})
     */
    protected array $notificationData;

    /**
     * Number of delivery attempts.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Number of delivery attempts"})
     */
    protected int $deliveryAttempts;

    /**
     * Date/time of the notification delivery (null if not yed delivered).
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=true, options={"comment": "Date/time of the notification delivery (null if not yed delivered)"})
     */
    protected ?DateTimeImmutable $sentOn;

    /**
     * Number of potential recipients notified (null if not yed delivered).
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", nullable=true, options={"unsigned": true, "comment": "Number of potential recipients notified (null if not yed delivered)"})
     */
    protected ?int $sentCountPotential;

    /**
     * Number of actual recipients notified (null if not yed delivered).
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", nullable=true, options={"unsigned": true, "comment": "Number of actual recipients notified (null if not yed delivered)"})
     */
    protected ?int $sentCountActual;

    /**
     * List of errors throws during delivery (empty if not yet delivered or if no errors occurred).
     *
     * @Doctrine\ORM\Mapping\Column(type="array", nullable=false, options={"comment": "List of errors throws during delivery (empty if not yet delivered or if no errors occurred)"})
     *
     * @var string[]
     */
    protected array $deliveryErrors;

    /**
     * Create a new (unsaved) instance.
     *
     * @param string $fqnClass The fully qualified name of the category class
     * @param array $notificationData Data specific to the notification class
     * @param int|null $priority The notification priority (bigger values for higher priorities)
     */
    public function __construct(string $fqnClass, array $notificationData = [], ?int $priority = null)
    {
        if ($priority === null) {
            $priority = 0;
            if (class_exists($fqnClass)) {
                $reflectionClass = new ReflectionClass($fqnClass);
                $classConstants = $reflectionClass->getConstants();
                if (is_int($classConstants['PRIORITY'] ?? null)) {
                    $priority = $classConstants['PRIORITY'];
                }
            }
        }
        $now = new DateTimeImmutable();
        $this->id = null;
        $this->createdOn = $now;
        $this->updatedOn = $now;
        $this->fqnClass = $fqnClass;
        $this->priority = $priority;
        $this->notificationData = $notificationData;
        $this->deliveryAttempts = 0;
        $this->sentOn = null;
        $this->sentCountPotential = null;
        $this->sentCountActual = null;
        $this->deliveryErrors = [];
    }

    /**
     * Get the Notification ID.
     */
    public function getID(): ?int
    {
        return $this->id;
    }

    /**
     * Get the date/time of the record creation.
     */
    public function getCreatedOn(): DateTimeImmutable
    {
        return $this->createdOn;
    }

    /**
     * Get date/time when the data was last modified.
     */
    public function getUpdatedOn(): DateTimeImmutable
    {
        return $this->updatedOn;
    }

    /**
     * Set date/time when the data was last modified.
     *
     * @return $this
     */
    public function setUpdatedOn(DateTimeImmutable $value): self
    {
        $this->updatedOn = $value;

        return $this;
    }

    /**
     * Get the fully qualified name of the category class.
     */
    public function getFQNClass(): string
    {
        return $this->fqnClass;
    }

    /**
     * Get the notification priority (bigger values for higher priorities).
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Get the data specific to the notification class.
     */
    public function getNotificationData(): array
    {
        return $this->notificationData;
    }

    /**
     * Set the data specific to the notification class.
     *
     * @return $this
     */
    public function setNotificationData(array $value): self
    {
        $this->notificationData = $value;

        return $this;
    }

    /**
     * Get the number of delivery attempts.
     */
    public function getDeliveryAttempts(): int
    {
        return $this->deliveryAttempts;
    }

    /**
     * Set the number of delivery attempts.
     *
     * @return $this
     */
    public function setDeliveryAttempts(int $value): self
    {
        $this->deliveryAttempts = $value;

        return $this;
    }

    /**
     * Get the date/time of the notification delivery (null if not yed delivered).
     */
    public function getSentOn(): ?DateTimeImmutable
    {
        return $this->sentOn;
    }

    /**
     * Set the date/time of the notification delivery (null if not yed delivered).
     *
     * @return $this
     */
    public function setSentOn(?DateTimeImmutable $value): self
    {
        $this->sentOn = $value;

        return $this;
    }

    /**
     * Get the number of potential recipients notified (null if not yed delivered).
     */
    public function getSentCountPotential(): ?int
    {
        return $this->sentCountPotential;
    }

    /**
     * Set the number of actual recipients notified (null if not yed delivered).
     *
     * @return $this
     */
    public function setSentCountPotential(?int $value): self
    {
        $this->sentCountPotential = $value;

        return $this;
    }

    /**
     * Get the number of actual recipients notified (null if not yed delivered).
     */
    public function getSentCountActual(): ?int
    {
        return $this->sentCountActual;
    }

    /**
     * Set the number of actual recipients notified (null if not yed delivered).
     *
     * @return $this
     */
    public function setSentCountActual(?int $value): self
    {
        $this->sentCountActual = $value;

        return $this;
    }

    /**
     * Get list of errors throws during delivery (empty if not yet delivered or if no errors occurred).
     *
     * @return string[]
     */
    public function getDeliveryErrors(): array
    {
        return $this->deliveryErrors;
    }

    /**
     * Add an error thrown during delivery.
     *
     * @return $this
     */
    public function addDeliveryError(string $value): self
    {
        $this->deliveryErrors[] = $value;

        return $this;
    }

    /**
     * Set list of errors throws during delivery (empty if not yet delivered or if no errors occurred).
     *
     * @param string[] $value
     *
     * @return $this
     */
    public function setDeliveryErrors(array $value): self
    {
        $this->deliveryErrors = $value;

        return $this;
    }
}
