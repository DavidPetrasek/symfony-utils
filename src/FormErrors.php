<?php 
namespace Psys\SymfonyUtils;

use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FormErrors
{    
    public function getArray(Form $baseForm) : array
    {
        $errsArr = [];
        $baseFormName = $baseForm->getName();
        $fileMultiple = [];

        $errs_FormErrorIterator = $baseForm->getErrors(true);
        foreach($errs_FormErrorIterator as $err_it)
        {
            $path = $err_it->getCause()->getPropertyPath();
            $path = preg_replace("/^(data.)|(.data)|(\\])|(\\[)|children/", '', $path);
            $path = str_replace('.', '_', $path);

            // Collect errors for file inputs with multiple files
            $invalidValue = $err_it->getCause()->getInvalidValue();
            $pathWithoutTrailingIntegers = preg_replace('/\d+$/', '', $path);

            if ($invalidValue instanceof UploadedFile  &&  $pathWithoutTrailingIntegers !== $path)
            {
                $fmMessage = ltrim($err_it->getMessage(), '_');

                $fileMultiple[$baseFormName.'_'.$pathWithoutTrailingIntegers][] = $fmMessage;
                continue;
            }

            $errsArr[] = 
            [
                'field_id' => $baseFormName.'_'.$path,
                'message' => $err_it->getMessage()               
            ];
        }

        // Concatenate and add collected errors of file inputs with multiple files into single field
        foreach($fileMultiple as $fieldID => $fm)
        {
            $errsArr[] = 
            [
                'field_id' => $fieldID,
                'message' => implode(' ',$fm)               
            ];
        }

        return $errsArr;
    }
}


?>