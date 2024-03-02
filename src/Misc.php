<?php 
namespace SymfonyUtils;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Utils\Result;

class Misc
{    
    function __construct
    (
        private ValidatorInterface $validator,
    )
    {}

    public function isEmailValid(string $email) : Result
    {
        $emailConstraint = new Assert\Email();        
        $errors = $this->validator->validate($email, $emailConstraint);
        
        if (empty($errors->count()))
        {
            return new Result(true);
        }
        else
        {
            return new Result(false, $errors[0]->getMessage());
        }
    }
}

?>