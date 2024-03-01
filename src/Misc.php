<?php 
namespace SymfonyUtils;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;


class Misc
{    
    function __construct
    (
        private ValidatorInterface $validator,
    )
    {}
    
    public function emailIsValid(string $email, bool $returnErrors = false) : bool|array
    {
        $emailConstraint = new Assert\Email();
        //         $emailConstraint->message = 'Neplatná e-mailová adresa';
        
        $errors = $this->validator->validate( $email, $emailConstraint );
        
        if (!$errors->count())
        {
            return true;
        }
        else
        {
            if ($returnErrors) {return $errors[0]->getMessage();}       //TODO: Result
            else               {return false;}
        }
    }
}

?>