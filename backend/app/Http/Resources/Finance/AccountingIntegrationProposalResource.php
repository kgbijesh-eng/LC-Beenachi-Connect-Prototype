<?php
namespace App\Http\Resources\Finance;
use Illuminate\Http\Request;use Illuminate\Http\Resources\Json\JsonResource;
class AccountingIntegrationProposalResource extends JsonResource { public function toArray(Request $request): array { return ['id'=>$this->public_id,'proposal_type'=>$this->proposal_type,'source_type'=>$this->source_type,'source_key'=>$this->source_key,'mapping_version'=>$this->mapping_version,'effective_date'=>$this->effective_date?->format('Y-m-d'),'expires_at'=>$this->expires_at?->format('Y-m-d'),'coverage_start_date'=>$this->coverage_start_date?->format('Y-m-d'),'status'=>$this->status,'lock_version'=>$this->lock_version,'created_at'=>$this->created_at?->toISOString(),'decided_at'=>$this->decided_at?->toISOString()]; } }
