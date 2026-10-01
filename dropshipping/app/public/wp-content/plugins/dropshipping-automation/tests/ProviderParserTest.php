<?php

use DSA\Providers\GeminiProvider;
use DSA\Providers\OllamaProvider;
use DSA\Providers\OpenRouterProvider;
use DSA\Providers\NvidiaNimProvider;
use PHPUnit\Framework\TestCase;

final class ProviderParserTest extends TestCase {
	public function test_gemini_parser_keeps_only_generate_content_models(): void {
		$models = GeminiProvider::parse_models(
			array(
				'models' => array(
					array( 'name' => 'models/gemini-2.5-flash', 'displayName' => 'Gemini 2.5 Flash', 'supportedGenerationMethods' => array( 'generateContent' ) ),
					array( 'name' => 'models/embedding-001', 'supportedGenerationMethods' => array( 'embedContent' ) ),
				),
			)
		);

		$this->assertCount( 1, $models );
		$this->assertSame( 'models/gemini-2.5-flash', $models[0]['id'] );
	}

	public function test_ollama_parser_extracts_model_details(): void {
		$models = OllamaProvider::parse_models(
			array(
				'models' => array(
					array( 'name' => 'llama3.2:latest', 'size' => 123456, 'modified_at' => '2026-09-29T10:00:00Z', 'details' => array( 'family' => 'llama', 'quantization_level' => 'Q4_K_M' ) ),
				),
			)
		);

		$this->assertSame( 'llama3.2:latest', $models[0]['id'] );
		$this->assertSame( 'llama', $models[0]['family'] );
		$this->assertSame( 'Q4_K_M', $models[0]['quantization'] );
		$this->assertSame( 123456, $models[0]['size'] );
	}

	public function test_openrouter_parser_reads_data_models(): void {
		$models = OpenRouterProvider::parse_models( array( 'data' => array( array( 'id' => 'openai/gpt-4.1-mini', 'name' => 'OpenAI: GPT-4.1 Mini' ) ) ) );
		$this->assertSame( 'openai/gpt-4.1-mini', $models[0]['id'] );
		$this->assertSame( 'OpenAI: GPT-4.1 Mini', $models[0]['name'] );
	}

	public function test_nvidia_nim_parser_reads_data_models(): void {
		$models = NvidiaNimProvider::parse_models( array( 'data' => array( array( 'id' => 'meta/llama-3.1-70b-instruct', 'owned_by' => 'meta' ) ) ) );
		$this->assertSame( 'meta/llama-3.1-70b-instruct', $models[0]['id'] );
		$this->assertSame( 'meta', $models[0]['description'] );
	}
}
