<?php

namespace HdBase\Entity;

use Doctrine\ORM\Mapping AS ORM;

abstract class AbstractEntity implements EntityInterface
{
    /**
     * @var Integer
     * @ORM\Id @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue
     */
    protected $id;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }
}