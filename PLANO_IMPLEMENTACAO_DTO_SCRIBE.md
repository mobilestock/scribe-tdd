# Plano de Implementacao: Suporte a Spatie Laravel-Data DTOs no Scribe-TDD

## Contexto

Atualmente, o `scribe-tdd` extrai body parameters apenas via **inline validators** (`$request->validate([...])`) dentro dos controllers. Quando um controller usa um DTO do `spatie/laravel-data` injetado por type-hint (ex: `public function store(ProductDTO $data)`), o Scribe nao consegue extrair automaticamente as regras de validacao nem gerar a documentacao dos parametros.

### Problema da solucao da comunidade (issue #872)
A solucao proposta por `ravibpatel` tem um bug critico: faz `new $className` para instanciar o Data object, mas DTOs do laravel-data sempre tem parametros obrigatorios no construtor, causando `ArgumentCountError`. Precisamos chamar `getValidationRules()` **estaticamente**, sem instanciar o objeto.

---

## Arquitetura da Solucao

Criaremos 3 novos arquivos no pacote `scribe-tdd`, seguindo o mesmo padrao arquitetural existente (Strategy pattern do Scribe):

```
packages/scribe-tdd/src/Strategies/
├── GetFromLaravelDataBase.php              # Base strategy (nova)
├── BodyParameters/
│   └── GetFromLaravelData.php              # Body params strategy (nova)
└── QueryParameters/
    └── GetFromLaravelData.php              # Query params strategy (nova)
```

---

## Passo 1: Criar a Strategy Base `GetFromLaravelDataBase`

**Arquivo:** `packages/scribe-tdd/src/Strategies/GetFromLaravelDataBase.php`

Esta classe base:
1. Usa Reflection para detectar parametros do tipo `Spatie\LaravelData\Data` nos metodos do controller
2. Extrai regras de validacao via `$className::getValidationRules([])` (chamada **estatica**, sem instanciar)
3. Usa o trait `ParsesValidationRules` do Scribe para converter regras em documentacao de parametros
4. Suporta metodo opcional `bodyParameters()` ou `queryParameters()` no DTO para descricoes e exemplos customizados

### Diferencas da solucao da comunidade:
- **NAO instancia o Data object** — chama `getValidationRules()` estaticamente
- Tratamento do `customParameterData`: tenta chamar o metodo estaticamente tambem, com fallback seguro
- Verificacao de existencia da classe `Spatie\LaravelData\Data` para evitar erro quando o pacote nao esta instalado

### Logica principal:
```php
protected function getRouteValidationRules(string $className): array
{
    if (method_exists($className, 'getValidationRules')) {
        return $className::getValidationRules([]);
    }
    return [];
}
```

### Deteccao de Data class no controller:
```php
protected function getLaravelDataReflectionClass(ReflectionFunctionAbstract $method): ?ReflectionClass
{
    foreach ($method->getParameters() as $argument) {
        $argType = $argument->getType();
        if ($argType === null || $argType instanceof ReflectionUnionType) continue;

        $argumentClassName = $argType->getName();
        if (!class_exists($argumentClassName)) continue;

        $argumentClass = new ReflectionClass($argumentClassName);
        if (class_exists(\Spatie\LaravelData\Data::class)
            && $argumentClass->isSubclassOf(\Spatie\LaravelData\Data::class)) {
            return $argumentClass;
        }
    }
    return null;
}
```

---

## Passo 2: Criar `BodyParameters\GetFromLaravelData`

**Arquivo:** `packages/scribe-tdd/src/Strategies/BodyParameters/GetFromLaravelData.php`

- Extende `GetFromLaravelDataBase`
- Define `$customParameterDataMethodName = 'bodyParameters'`
- Implementa `isLaravelDataMeantForThisStrategy()`:
  - Retorna `false` se o docblock do DTO contiver "query parameters"
  - Retorna `false` se o DTO tiver metodo `queryParameters()` mas nao tiver `bodyParameters()`
  - Retorna `true` como fallback (body params e o padrao)

---

## Passo 3: Criar `QueryParameters\GetFromLaravelData`

**Arquivo:** `packages/scribe-tdd/src/Strategies/QueryParameters/GetFromLaravelData.php`

- Extende `GetFromLaravelDataBase`
- Define `$customParameterDataMethodName = 'queryParameters'`
- Implementa `isLaravelDataMeantForThisStrategy()`:
  - Retorna `true` se docblock contiver "query parameters"
  - Retorna `true` se DTO tiver metodo `queryParameters()`
  - Retorna `false` como fallback

---

## Passo 4: Registrar as novas strategies no `ScribeTddServiceProvider`

**Arquivo:** `packages/scribe-tdd/src/ScribeTddServiceProvider.php`

Adicionar as novas strategies **antes** das existentes, para que sejam avaliadas primeiro:

```php
'bodyParameters' => [
    Strategies\BodyParameters\GetFromLaravelData::class,      // NOVO
    Strategies\BodyParameters\GetFromInlineValidator::class,   // existente
],
'queryParameters' => [
    Strategies\QueryParameters\GetFromLaravelData::class,     // NOVO
    Strategies\QueryParameters\GetFromInlineValidator::class,  // existente
    \AjCastro\ScribeTdd\Strategies\QueryParameters\GetFromTestResult::class,
],
```

**Ordem importa:** Se o controller usar Data DTO, a nova strategy retorna os params e as demais sao ignoradas. Se nao usar DTO, retorna `[]` e o fallback para inline validator continua funcionando normalmente.

---

## Passo 5: Normalizar regras de validacao do laravel-data para formato Scribe

O `getValidationRules()` do laravel-data retorna regras no formato:
```php
[
    'product_variation_id' => ['required', 'string'],
    'attribute_1_value' => ['nullable', 'string'],
]
```

Este formato ja e compativel com o `ParsesValidationRules` do Scribe — a conversao e direta. Porem, regras podem conter objetos `ValidationRule` do laravel-data (como `Min`, `Max`, etc.). Precisamos garantir que:
- Regras que sao strings passam direto
- Regras que sao objetos `StringableRule` ou implementam `__toString()` sao convertidas para string
- Regras que sao objetos do Laravel (`Rule::in(...)`, etc.) sao passadas como estao (Scribe ja sabe lidar)

Implementar um metodo `normalizeDataRules()` na base strategy para esta conversao.

---

## Passo 6: Suporte a metodos customizados no DTO

Para que o usuario possa adicionar descricoes e exemplos aos parametros, os DTOs poderao ter metodos opcionais:

```php
class ProductDTO extends Data
{
    public function __construct(
        public readonly string $product_variation_id,
        public readonly ?string $sku
    ) {}

    // Opcional: adiciona descricoes e exemplos para documentacao
    public static function bodyParameters(): array
    {
        return [
            'product_variation_id' => [
                'description' => 'ID da variacao do produto',
                'example' => 'var_123abc',
            ],
            'sku' => [
                'description' => 'Codigo SKU do produto',
                'example' => 'SKU-001',
            ],
        ];
    }
}
```

**Nota:** O metodo deve ser `static` para evitar instanciacao do DTO.

---

## Resumo dos Arquivos

| Acao     | Arquivo                                                              |
|----------|----------------------------------------------------------------------|
| CRIAR    | `packages/scribe-tdd/src/Strategies/GetFromLaravelDataBase.php`      |
| CRIAR    | `packages/scribe-tdd/src/Strategies/BodyParameters/GetFromLaravelData.php` |
| CRIAR    | `packages/scribe-tdd/src/Strategies/QueryParameters/GetFromLaravelData.php` |
| EDITAR   | `packages/scribe-tdd/src/ScribeTddServiceProvider.php`               |

## Fluxo Final

```
Controller: public function store(ProductDTO $data)
                                      │
                    ┌─────────────────┘
                    ▼
    GetFromLaravelData Strategy
    │
    ├─ 1. Reflection: detecta ProductDTO extends Data
    ├─ 2. ProductDTO::getValidationRules([]) → regras Laravel
    ├─ 3. ProductDTO::bodyParameters() → descricoes/exemplos (opcional)
    ├─ 4. ParsesValidationRules::getParametersFromValidationRules()
    └─ 5. normaliseArrayAndObjectParameters() → parametros documentados
                    │
                    ▼
           Documentacao gerada automaticamente
```

## Riscos e Mitigacoes

| Risco | Mitigacao |
|-------|-----------|
| `spatie/laravel-data` nao instalado | Verificar `class_exists(Data::class)` antes de qualquer operacao |
| Regras com objetos complexos | `normalizeDataRules()` converte para formato compativel |
| DTO com `rules()` customizado | `getValidationRules()` ja considera o metodo `rules()` internamente |
| DTOs nested (Data dentro de Data) | `getValidationRules()` ja resolve recursivamente |
