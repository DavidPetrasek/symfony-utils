<?php 
namespace Psys\SymfonyUtils;

use Symfony\Component\Form\Form;

class FormErrors
{    
    public function getArray(Form $baseForm) : array
    {
        $errsArr = [];
        $baseFormName = $baseForm->getName();

        $errs_FormErrorIterator = $baseForm->getErrors(true);
        foreach($errs_FormErrorIterator as $err_it)
        {
            $path = $err_it->getCause()->getPropertyPath();
            $path = preg_replace("/^(data.)|(.data)|(\\])|(\\[)|children/", '', $path);
            $path = str_replace('.', '_', $path);

            $errsArr[] = 
            [
                'field_id' => $baseFormName.'_'.$path,
                'message' => $err_it->getMessage()               
            ];
        }

        return $errsArr;
    }
}


?>