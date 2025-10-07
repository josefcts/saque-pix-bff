<?php

declare(strict_types=1);

namespace App\Interface\Http\Request;

use Hyperf\Validation\Request\FormRequest;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Validator;
use Carbon\CarbonImmutable;

final class WithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => 'required|string|in:pix',
            'amount' => 'required|numeric|min:0.01',

            // Somente PIX do tipo email
            'pix' => 'required|array',
            'pix.key' => 'required|email',
            'pix.type' => 'required|in:email',
            'pix.provider' => 'nullable|string|max:64',

            // ISO 8601 (com timezone) ou nulo
            'schedule' => 'nullable|date_format:Y-m-d\TH:i:sP',
        ];
    }

    public function messages(): array
    {
        return [
            'method.in' => 'O método deve ser "pix".',
            'amount.min' => 'O valor deve ser maior que zero.',
            'pix.required' => 'Os dados de PIX são obrigatórios.',
            'pix.key.email' => 'A chave PIX deve ser um e-mail válido.',
            'pix.type.in' => 'Somente chaves PIX do tipo "email" são aceitas.',
            'schedule.date_format' => 'Formato de data inválido (use ISO8601, ex: 2025-10-07T12:00:00-03:00).',
        ];
    }

    /**
     * Regras adicionais de negócio após validação básica:
     * - schedule não pode ser no passado
     * - schedule não pode exceder 7 dias no futuro
     */
    public function withValidator($validator): void
    {
        /** @var Validator $validator */
        $validator->after(function (Validator $v) {
            $schedule = $this->input('schedule');
            if ($schedule === null || $schedule === '') {
                return;
            }

            try {
                $now = CarbonImmutable::now('America/Sao_Paulo');
                $when = CarbonImmutable::parse($schedule, 'America/Sao_Paulo');

                if ($when->lt($now)) {
                    $v->errors()->add('schedule', 'Agendamento não pode ser no passado.');
                    return;
                }

                if ($when->diffInDays($now) > 7) {
                    $v->errors()->add('schedule', 'Agendamento não pode exceder 7 dias.');
                    return;
                }
            } catch (\Throwable $e) {
                $v->errors()->add('schedule', 'Data de agendamento inválida.');
            }
        });
    }
}
