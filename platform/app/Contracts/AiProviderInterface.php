<?php
namespace App\Contracts;
interface AiProviderInterface {public function suggest(array $context): array; public function analyzeBookPdf(array $context): array;}
