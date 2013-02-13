<?php

namespace HdBase\Entity\Exception;

use DysBase\Entity\Exception;

class NotFound
    extends UnexpectedValueException
    implements ExceptionInterface
{
}