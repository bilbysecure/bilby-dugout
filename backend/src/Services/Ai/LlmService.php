<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * AI assistance boundary. MockLlmService (no creds) generates templated content;
 * a real Anthropic Claude client swaps in here once ANTHROPIC_API_KEY is set.
 */
interface LlmService
{
    /** @return array{title:string,description:string,target_audience:string,key_messaging:string,tone:string} */
    public function draftRequest(array $input): array;

    /** @return array<int,array{title:string,detail:string}> */
    public function recommendations(array $subscription): array;

    /** @return array{status:string,summary:string,score:int,matches:string[],issues:string[],recommendations:string[]} */
    public function reviewBrandAsset(array $asset, array $brandKit): array;
}
