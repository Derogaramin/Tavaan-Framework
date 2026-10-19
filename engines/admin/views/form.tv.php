@extends('admin::layout')

@section('title', ($model === null ? 'ایجاد ' : 'ویرایش ') . $resource->titleText())

@section('content')
    <h1 class="admin-title">
        {{ $resource->iconGlyph() }}
        {{ $model === null ? 'ایجاد' : 'ویرایش' }} {{ $resource->titleText() }}
    </h1>

    <div class="admin-card">
        <form method="post"
              action="/{{ $adminPrefix }}/{{ $resource->key() }}{{ $model !== null ? '/' . $model->getKey() : '' }}">
            <input type="hidden" name="_token" value="{{ $token }}">
            @if($model !== null)
                <input type="hidden" name="_method" value="PUT">
            @endif

            @foreach($form->fields() as $field)
                <div class="field">
                    @if($field->type !== 'hidden' && $field->type !== 'static')
                        <label for="f-{{ $field->name }}">{{ $field->labelText() }}@if($field->isRequired()) <span class="error-text">*</span>@endif</label>
                    @endif

                    @if(in_array($field->type, ['text', 'email', 'slug', 'date', 'time', 'datetime', 'password', 'color', 'hidden'], true))
                        <input id="f-{{ $field->name }}"
                               type="{{ $field->type === 'slug' ? 'text' : ($field->type === 'date' ? 'date' : ($field->type === 'datetime' ? 'datetime-local' : ($field->type === 'time' ? 'time' : $field->type))) }}"
                               name="{{ $field->name }}"
                               value="{{ $values[$field->name] ?? '' }}"
                               @if($field->type === 'password') autocomplete="new-password"@endif>

                    @elseif($field->type === 'jalali_date')
                        <input id="f-{{ $field->name }}" type="text" name="{{ $field->name }}"
                               value="{{ $values[$field->name] ?? '' }}" placeholder="1403/04/12"
                               inputmode="numeric" style="max-width:220px">
                        <div class="muted" style="font-size:12px">تاریخ جلالی ({{ $field->jalaliFormatValue() }}) — در DB به میلادی UTC ذخیره می‌شود</div>

                    @elseif($field->type === 'number' || $field->type === 'money')
                        <input id="f-{{ $field->name }}" type="number" step="{{ $field->type === 'money' ? '0.01' : '1' }}"
                               name="{{ $field->name }}" value="{{ $values[$field->name] ?? '' }}">
                        @if($field->type === 'money')
                            <div class="muted" style="font-size:12px">{{ $field->currencyCode() }}</div>
                        @endif

                    @elseif(in_array($field->type, ['textarea', 'richtext', 'code', 'json'], true))
                        <textarea id="f-{{ $field->name }}" name="{{ $field->name }}"
                                  rows="{{ $field->attrs()['rows'] ?? ($field->type === 'textarea' ? 4 : 8) }}"
                                  @if($field->type === 'code' || $field->type === 'json') dir="ltr" style="font-family:monospace"@endif>{{ $values[$field->name] ?? '' }}</textarea>
                        @if($field->type === 'richtext')
                            <div class="muted" style="font-size:12px">HTML — با Sanitizer پروفایل «{{ $field->sanitizerProfile() ?? 'rich_text' }}» پاک‌سازی می‌شود 🔒</div>
                        @endif

                    @elseif($field->type === 'tags')
                        <input id="f-{{ $field->name }}" type="text" name="{{ $field->name }}"
                               value="{{ $values[$field->name] ?? '' }}" placeholder="برچسب‌ها با «,» جدا شوند">

                    @elseif($field->type === 'select' || $field->type === 'relation')
                        <select id="f-{{ $field->name }}" name="{{ $field->name }}">
                            <option value="">—</option>
                            @if($field->type === 'relation')
                                @foreach($relationChoices[$field->name] ?? [] as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" {{ (string) ($values[$field->name] ?? '') === (string) $optionValue ? 'selected' : '' }}>{{ $optionLabel }}</option>
                                @endforeach
                            @else
                                @foreach($field->optionsList() as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" {{ (string) ($values[$field->name] ?? '') === (string) $optionValue ? 'selected' : '' }}>{{ $optionLabel }}</option>
                                @endforeach
                            @endif
                        </select>

                    @elseif($field->type === 'multiselect')
                        <select id="f-{{ $field->name }}" name="{{ $field->name }}[]" multiple size="5">
                            @foreach($field->optionsList() as $optionValue => $optionLabel)
                                <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                            @endforeach
                        </select>

                    @elseif($field->type === 'radio')
                        @foreach($field->optionsList() as $optionValue => $optionLabel)
                            <label style="display:inline-block;margin-inline-end:14px">
                                <input type="radio" name="{{ $field->name }}" value="{{ $optionValue }}"
                                       {{ (string) ($values[$field->name] ?? '') === (string) $optionValue ? 'checked' : '' }}>
                                {{ $optionLabel }}
                            </label>
                        @endforeach

                    @elseif($field->type === 'checkbox' || $field->type === 'switch')
                        <label style="display:inline-block">
                            <input type="checkbox" name="{{ $field->name }}" value="1"
                                   {{ !empty($values[$field->name]) ? 'checked' : '' }}>
                            {{ $field->type === 'switch' ? 'فعال' : 'بله' }}
                        </label>

                    @elseif($field->type === 'file' || $field->type === 'media')
                        <input id="f-{{ $field->name }}" type="text" name="{{ $field->name }}"
                               value="{{ $values[$field->name] ?? '' }}" placeholder="مسیر فایل (disk: {{ $field->diskName() ?? 'public' }})" dir="ltr">
                        <div class="muted" style="font-size:12px">آپلود باینری از طریق موتور media/filesystem انجام و مسیر آن اینجا ثبت می‌شود</div>

                    @elseif($field->type === 'repeater')
                        @php $repRows = is_array($values[$field->name] ?? null) ? $values[$field->name] : []; @endphp
                        <table class="admin-table">
                            @foreach($repRows as $repIndex => $repRow)
                                <tr>
                                    @foreach(($field->repeater()?->fields() ?? []) as $subField)
                                        <td>
                                            <input type="text" name="{{ $field->name }}[{{ $repIndex }}][{{ $subField->name }}]"
                                                   value="{{ is_array($repRow) ? ($repRow[$subField->name] ?? '') : '' }}"
                                                   placeholder="{{ $subField->labelText() }}">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            <tr>
                                @foreach(($field->repeater()?->fields() ?? []) as $subField)
                                    <td>
                                        <input type="text" name="{{ $field->name }}[{{ count($repRows) }}][{{ $subField->name }}]"
                                               placeholder="{{ $subField->labelText() }} (جدید)">
                                    </td>
                                @endforeach
                            </tr>
                        </table>

                    @elseif($field->type === 'static')
                        <p class="muted" style="margin:2px 0">{{ $values[$field->name] ?? '' }}</p>
                    @endif

                    @if(isset($errors[$field->name]))
                        <div class="error-text">{{ is_array($errors[$field->name]) ? implode(' — ', $errors[$field->name]) : $errors[$field->name] }}</div>
                    @endif
                    @if(isset($field->attrs()['help']))
                        <div class="muted" style="font-size:12px">{{ $field->attrs()['help'] }}</div>
                    @endif
                </div>
            @endforeach

            <div class="toolbar" style="margin-top:18px">
                <button class="btn primary" type="submit">{{ $model === null ? 'ایجاد' : 'ذخیرهٔ تغییرات' }}</button>
                <a class="btn" href="/{{ $adminPrefix }}/{{ $resource->key() }}">بازگشت</a>
            </div>
        </form>
    </div>
@endsection
