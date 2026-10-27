<template>
  <form class="eye-exam-form" novalidate @submit.prevent="submit">
    <p id="ee-hint" class="eye-exam-form__hint">
      Dioptrias em passos de 0.25: digite o valor ou use as setas ↑/↓ do teclado
      (ou os botões − e +). O eixo só é habilitado quando o cilíndrico é
      diferente de 0.
    </p>
    <p id="ee-acuity-hint" class="sr-only">
      Acuidade na escala de Snellen 20/N: informe só o N, de 10 a 400.
    </p>

    <div class="eye-grid">
      <fieldset
        v-for="eye in eyes"
        :key="eye"
        class="eye-card"
        :class="`eye-card--${eye}`"
      >
        <legend class="eye-card__legend">
          <span class="eye-card__tag" aria-hidden="true">{{
            eyeLabels[eye].short
          }}</span>
          {{ eyeLabels[eye].long }}
          <span class="sr-only">({{ eyeLabels[eye].short }})</span>
        </legend>

        <div class="eye-card__fields">
          <FormField
            v-for="field in acuityFields"
            :id="fieldId(eye, field.key)"
            :key="field.key"
            class="eye-field eye-field--half"
            :label="field.label"
            :error="errorOf(eye, field.key)"
          >
            <div class="affix-input">
              <span class="affix-input__prefix" aria-hidden="true">20/</span>
              <b-form-input
                :id="fieldId(eye, field.key)"
                v-model="form[eye][field.key]"
                inputmode="numeric"
                maxlength="6"
                placeholder="N"
                autocomplete="off"
                :state="errorOf(eye, field.key) ? false : null"
                :aria-describedby="
                  describedBy(eye, field.key, 'ee-acuity-hint')
                "
                :formatter="acuityDenominator"
                @input="clearError(`${eye}.${field.key}`)"
              />
            </div>
          </FormField>

          <FormField
            v-for="field in refractionFields"
            :id="fieldId(eye, field)"
            :key="field"
            class="eye-field eye-field--third"
            :label="rules[field].label"
            :error="errorOf(eye, field)"
          >
            <DiopterInput
              :id="fieldId(eye, field)"
              :value="form[eye][field]"
              :field="field"
              :label="`${rules[field].label.toLowerCase()} do ${eyeLabels[
                eye
              ].long.toLowerCase()}`"
              :invalid="!!errorOf(eye, field)"
              :describedby="describedBy(eye, field, 'ee-hint')"
              @input="onDiopterInput(eye, field, $event)"
            />
          </FormField>

          <FormField
            :id="fieldId(eye, 'axis')"
            class="eye-field eye-field--third"
            label="Eixo"
            :error="errorOf(eye, 'axis')"
          >
            <div class="affix-input affix-input--suffix">
              <b-form-input
                :id="fieldId(eye, 'axis')"
                v-model="form[eye].axis"
                inputmode="numeric"
                maxlength="3"
                autocomplete="off"
                :placeholder="axisOn(eye) ? '0–180' : '—'"
                :disabled="!axisOn(eye)"
                :state="errorOf(eye, 'axis') ? false : null"
                :aria-describedby="describedBy(eye, 'axis', 'ee-hint')"
                :formatter="digitsOnly"
                @input="clearError(`${eye}.axis`)"
              />
              <span class="affix-input__suffix" aria-hidden="true">°</span>
            </div>
          </FormField>

          <FormField
            :id="fieldId(eye, 'addition')"
            class="eye-field eye-field--half"
            :label="rules.addition.label"
            :error="errorOf(eye, 'addition')"
          >
            <DiopterInput
              :id="fieldId(eye, 'addition')"
              :value="form[eye].addition"
              field="addition"
              :label="`adição do ${eyeLabels[eye].long.toLowerCase()}`"
              :invalid="!!errorOf(eye, 'addition')"
              :describedby="describedBy(eye, 'addition', 'ee-hint')"
              @input="onDiopterInput(eye, 'addition', $event)"
            />
          </FormField>

          <FormField
            :id="fieldId(eye, 'iop')"
            class="eye-field eye-field--half"
            label="PIO"
            :error="errorOf(eye, 'iop')"
          >
            <div class="affix-input affix-input--suffix">
              <b-form-input
                :id="fieldId(eye, 'iop')"
                v-model="form[eye].iop"
                inputmode="numeric"
                maxlength="2"
                autocomplete="off"
                placeholder="5–60"
                :state="errorOf(eye, 'iop') ? false : null"
                :aria-describedby="describedBy(eye, 'iop', 'ee-iop-unit')"
                :formatter="digitsOnly"
                @input="clearError(`${eye}.iop`)"
              />
              <span class="affix-input__suffix" aria-hidden="true">mmHg</span>
            </div>
          </FormField>
        </div>
      </fieldset>
    </div>
    <p id="ee-iop-unit" class="sr-only">
      Pressão intraocular em milímetros de mercúrio (mmHg).
    </p>

    <FormField
      id="ee-diagnosis"
      label="Diagnóstico"
      optional
      :error="errors.diagnosis"
    >
      <b-form-textarea
        id="ee-diagnosis"
        v-model="form.diagnosis"
        rows="2"
        max-rows="8"
        maxlength="2000"
        placeholder="Descrever"
        :state="errors.diagnosis ? false : null"
        :aria-describedby="errors.diagnosis ? 'ee-diagnosis-error' : undefined"
        @input="clearError('diagnosis')"
      />
    </FormField>

    <FormField
      id="ee-observations"
      label="Observações"
      optional
      :error="errors.observations"
    >
      <b-form-textarea
        id="ee-observations"
        v-model="form.observations"
        rows="2"
        max-rows="10"
        maxlength="5000"
        placeholder="Descrever"
        :state="errors.observations ? false : null"
        :aria-describedby="
          errors.observations ? 'ee-observations-error' : undefined
        "
        @input="clearError('observations')"
      />
    </FormField>

    <footer class="form-footer">
      <button
        v-if="cancelable"
        type="button"
        class="btn-link-danger"
        @click="$emit('cancel')"
      >
        Cancelar
      </button>
      <span v-else />
      <b-button type="submit" variant="primary" :disabled="saving">
        <b-spinner v-if="saving" small class="mr-1" label="Salvando" />
        Salvar prontuário
      </b-button>
    </footer>
  </form>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue";
import FormField from "@/components/FormField.vue";
import DiopterInput from "@/components/DiopterInput.vue";
import {
  consultationService,
  type Eye,
  type EyeExam,
} from "@/services/consultationService";
import { getErrorMessage, getValidationErrors } from "@/services/api";
import { notify } from "@/utils/notify";
import {
  acuityDenominator,
  axisEnabled,
  DIOPTER_RULES,
  EYE_LABELS,
  EYES,
  eyeExamFieldId,
  mapEyeExamErrors,
  toEyeExamForm,
  toEyeExamPayload,
  validateEyeExamForm,
  type DiopterField,
  type EyeExamErrors,
  type EyeExamForm,
  type EyeField,
} from "@/utils/eyeExam";

/**
 * Ophthalmic exam form (OD / OE side by side, stacked on mobile) + diagnosis
 * and observations. Saves with PUT /consultations/{id}/eye-exam and emits
 * `saved` with the updated consultation.
 */
export default defineComponent({
  name: "EyeExamForm",
  components: { FormField, DiopterInput },
  props: {
    consultationId: { type: [Number, String], required: true },
    exam: { type: Object as PropType<EyeExam | null>, default: null },
    /** Editing a saved exam: show "Cancelar" (back to the read-only view). */
    cancelable: { type: Boolean, default: false },
  },
  data() {
    return {
      form: toEyeExamForm(this.exam) as EyeExamForm,
      errors: {} as EyeExamErrors,
      saving: false,
      eyes: EYES,
      eyeLabels: EYE_LABELS,
      rules: DIOPTER_RULES,
      acuityFields: [
        { key: "visual_acuity_sc", label: "Acuidade sem correção" },
        { key: "visual_acuity_cc", label: "Acuidade com correção" },
      ] as { key: EyeField; label: string }[],
      refractionFields: ["spherical", "cylindrical"] as DiopterField[],
    };
  },
  methods: {
    fieldId(eye: Eye, field: string): string {
      return eyeExamFieldId(`${eye}.${field}`);
    },
    errorOf(eye: Eye, field: string): string {
      return this.errors[`${eye}.${field}`] ?? "";
    },
    describedBy(eye: Eye, field: string, hint: string): string {
      return this.errorOf(eye, field)
        ? `${this.fieldId(eye, field)}-error ${hint}`
        : hint;
    },
    axisOn(eye: Eye): boolean {
      return axisEnabled(this.form[eye]);
    },
    clearError(key: string): void {
      if (this.errors[key]) this.$delete(this.errors, key);
    },
    onDiopterInput(eye: Eye, field: DiopterField, value: string): void {
      this.form[eye][field] = value;
      this.clearError(`${eye}.${field}`);
      if (field === "cylindrical" && !axisEnabled(this.form[eye])) {
        // Axis is forbidden without cylinder: drop it with its error.
        this.form[eye].axis = "";
        this.clearError(`${eye}.axis`);
      }
    },
    acuityDenominator,
    digitsOnly(value: string): string {
      return value.replace(/\D/g, "");
    },
    focusFirstError(): void {
      this.$nextTick(() => {
        const el = this.$el.querySelector(".is-invalid") as HTMLElement | null;
        el?.focus();
      });
    },
    async submit(): Promise<void> {
      if (this.saving) return;
      const errors = validateEyeExamForm(this.form);
      this.errors = errors;
      if (Object.keys(errors).length) {
        notify(this.$root, "error", "Verifique os campos destacados.");
        this.focusFirstError();
        return;
      }
      this.saving = true;
      try {
        const consultation = await consultationService.saveEyeExam(
          this.consultationId,
          toEyeExamPayload(this.form)
        );
        notify(this.$root, "success", "Prontuário salvo com sucesso!");
        this.$emit("saved", consultation);
      } catch (error) {
        const bag = getValidationErrors(error);
        if (bag) {
          const { status, ...fields } = mapEyeExamErrors(bag);
          this.errors = fields;
          notify(
            this.$root,
            "error",
            status || "Verifique os campos destacados para salvar!"
          );
          if (Object.keys(fields).length) this.focusFirstError();
        } else {
          notify(
            this.$root,
            "error",
            getErrorMessage(error, "Não foi possível salvar o prontuário.")
          );
        }
      } finally {
        this.saving = false;
      }
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.eye-exam-form__hint {
  margin: 0 0 16px;
  color: $text-muted;
  font-size: 14px;
}

.eye-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 8px;
}

.eye-card {
  min-width: 0;
  margin: 0;
  padding: 16px 16px 0;
  border: 1px solid $border-light;
  border-radius: $border-radius-lg;
}

.eye-card__legend {
  display: flex;
  gap: 10px;
  align-items: center;
  width: auto;
  margin: 0 0 12px;
  padding: 0 6px;
  color: $navy;
  font-size: 16px;
  font-weight: 700;
}

.eye-card__tag {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  height: 24px;
  padding: 0 6px;
  color: #fff;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.04em;
  background: $navy;
  border-radius: 4px;

  .eye-card--oe & {
    background: $blue-dark;
  }
}

.eye-card__fields {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  column-gap: 8px;
}

.eye-field--half {
  grid-column: span 3;
}

.eye-field--third {
  grid-column: span 2;
}

.affix-input {
  position: relative;
  display: flex;
  align-items: stretch;

  ::v-deep .form-control {
    padding-left: 39px;
  }

  &--suffix ::v-deep .form-control {
    padding-right: 52px;
    padding-left: 15px;
  }
}

.affix-input__prefix,
.affix-input__suffix {
  position: absolute;
  top: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  color: $text-muted;
  font-size: 14px;
  font-weight: 600;
  pointer-events: none;
}

.affix-input__prefix {
  left: 15px;
  color: $text;
  font-size: 16px;
  font-weight: 400;
}

.affix-input__suffix {
  right: 12px;
}

.form-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: center;
  justify-content: space-between;
  padding-top: 8px;
}

@media (max-width: 991.98px) {
  .eye-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 575.98px) {
  .eye-card {
    padding: 12px 12px 0;
  }

  .eye-field--half,
  .eye-field--third {
    grid-column: span 3;
  }
}
</style>
