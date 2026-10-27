{{-- Data de nascimento / fundação do pretendente (form-segimob — em posições
     diferentes pra residencial e comercial). Obrigatória na Segimob. --}}
                <div class="field">
                    <label class="field-label" for="data_nascimento">
                        Data de nascimento / fundação <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('calendar-blank') !!}</span>
                        <input class="field-input has-icon" type="text" id="data_nascimento" name="data_nascimento"
                               data-mask="date" maxlength="10" inputmode="numeric" placeholder="DD/MM/AAAA" required>
                    </div>
                </div>
