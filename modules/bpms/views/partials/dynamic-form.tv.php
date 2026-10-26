@php
    /**
     * فرم پویا از FormSchema (DOC-098 §4) — رندر widget per نوع فیلد.
     * ورودی: $form (Modules\Bpms\Domain\ValueObjects\FormSchema)، $values (array)، $errors (array|null)
     */
    $values = $values ?? [];
    $formErrors = $errors ?? [];
@endphp
@foreach($form->fields as $field)
    @php
        $name = $field->name;
        $value = $values[$name] ?? ($field->default ?? '');
        $fieldErrors = $formErrors[$name] ?? [];
    @endphp
    <div class="form-row">
        <label for="fld_{{ $name }}">
            <span>{{ $field->label }} @if($field->required)<span class="req">*</span>@endif</span>

            @if($field->type->value === 'textarea')
                <textarea id="fld_{{ $name }}" name="{{ $name }}" rows="{{ $field->rows }}"
                          placeholder="{{ $field->placeholder ?? '' }}">{{ is_scalar($value) ? (string) $value : '' }}</textarea>

            @elseif($field->type->value === 'select')
                <select id="fld_{{ $name }}" name="{{ $name }}">
                    <option value="">—</option>
                    @foreach($field->options as $option)
                        <option value="{{ $option }}" {{ (string) $value === $option ? 'selected' : '' }}>{{ $option }}</option>
                    @endforeach
                </select>

            @elseif($field->type->value === 'checkbox')
                <input type="hidden" name="{{ $name }}" value="0">
                <input type="checkbox" id="fld_{{ $name }}" name="{{ $name }}" value="1"
                       style="width:auto" {{ !empty($value) ? 'checked' : '' }}>

            @elseif($field->type->value === 'number')
                <input type="number" id="fld_{{ $name }}" name="{{ $name }}"
                       value="{{ is_scalar($value) ? (string) $value : '' }}"
                       @if($field->min !== null) min="{{ $field->min }}" @endif
                       @if($field->max !== null) max="{{ $field->max }}" @endif
                       placeholder="{{ $field->placeholder ?? '' }}">

            @elseif($field->type->value === 'email')
                <input type="email" id="fld_{{ $name }}" name="{{ $name }}"
                       value="{{ is_scalar($value) ? (string) $value : '' }}"
                       placeholder="{{ $field->placeholder ?? '' }}">

            @elseif($field->type->value === 'mobile')
                <input type="tel" id="fld_{{ $name }}" name="{{ $name }}" inputmode="numeric"
                       value="{{ is_scalar($value) ? (string) $value : '' }}"
                       placeholder="{{ $field->placeholder ?? '09xxxxxxxxx' }}">

            @elseif($field->type->value === 'jalali_date')
                <input type="text" id="fld_{{ $name }}" name="{{ $name }}"
                       value="{{ is_scalar($value) ? (string) $value : '' }}"
                       placeholder="{{ $field->placeholder ?? '1405/08/04' }}" style="max-width:180px">

            @elseif($field->type->value === 'date')
                <input type="date" id="fld_{{ $name }}" name="{{ $name }}"
                       value="{{ is_scalar($value) ? (string) $value : '' }}">

            @elseif($field->type->value === 'hidden')
                <input type="hidden" name="{{ $name }}" value="{{ is_scalar($value) ? (string) $value : '' }}">

            @else
                <input type="text" id="fld_{{ $name }}" name="{{ $name }}"
                       value="{{ is_scalar($value) ? (string) $value : '' }}"
                       placeholder="{{ $field->placeholder ?? '' }}"
                       @if($field->max !== null) maxlength="{{ $field->max }}" @endif>
            @endif

            @if($field->help !== null)
                <span class="help">{{ $field->help }}</span>
            @endif
            @foreach((array) $fieldErrors as $err)
                <span class="help" style="color:var(--danger)">{{ $err }}</span>
            @endforeach
        </label>
    </div>
@endforeach
