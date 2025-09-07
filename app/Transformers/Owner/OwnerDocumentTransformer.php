<?php

namespace App\Transformers\Owner;

use App\Transformers\Transformer;
use App\Models\Admin\OwnerDocument;
use App\Base\Constants\Masters\DriverDocumentStatus;
use App\Base\Constants\Masters\DriverDocumentStatusString;

class OwnerDocumentTransformer extends Transformer
{
    /**
     * Resources that can be included if requested.
     *
     * @var array
     */
    protected array $availableIncludes = [

    ];

    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform(OwnerDocument $document)
    {
        $params = [
            'id' => $document->id,
            'document_id' => $document->document_id,
            'document_name' => $document->document_name,
            'document' => $document->image,
            'back_document' => $document->back_image,
            'identify_number' => $document->identify_number,
            'expiry_date' => $document->expiry_date,
            'comment'=>$document->comment,
            'document_status'=>$document->document_status
            // 'identify_number_key' => $document->identify_number_key,
        ];
        $userlang = 'en';
        if(auth()->user()){
            $userlang = auth()->user()->lang ?? 'en';
        }

        foreach (DriverDocumentStatus::DocumentStatus() as $key=> $document_string) {
            if ($document->document_status==$key) {
                $params['document_status_string'] = custom_status_trans($document_string,[],$userlang);
            }
        }
        return $params;
    }
}
