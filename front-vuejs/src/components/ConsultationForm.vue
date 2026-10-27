<template>
  <form class="consultation-form" novalidate @submit.prevent="submit">
    <component :is="headingTag" :class="headingClass">{{ title }}</component>
    <slot name="intro" />

    <p v-if="locked" class="consultation-form__notice">
      Atendimento finalizado: apenas as anotações podem ser alteradas.
    </p>

    <div class="consultation-form__body">
      <div class="consultation-grid consultation-grid--when">
        <FormField id="cf-date" label="Data" :error="errors.date">
          <div class="date-input">
            <b-form-input
              id="cf-date"
              v-model="form.date"
              required
              placeholder="DD/MM/AAAA"
              inputmode="numeric"
              maxlength="10"
              autocomplete="off"
              :disabled="locked"
              :formatter="maskDate"
              :state="errors.date || errors.scheduled_at ? false : null"
              :aria-describedby="dateDescribedBy"
              @input="clearError('date', 'scheduled_at')"
            />
            <b-form-datepicker
              v-if="!locked"
              ref="picker"
              :value="dateIso"
              class="date-input__picker"
              button-only
              right
              locale="pt-BR"
              :start-weekday="1"
              button-variant="link"
              label-help="Use as setas do teclado para navegar"
              label-no-date-selected="Nenhuma data selecionada"
              label-selected="Data selecionada"
              label-prev-year="Ano anterior"
              label-prev-month="Mês anterior"
              label-current-month="Mês atual"
              label-next-month="Próximo mês"
              label-next-year="Próximo ano"
              label-today="Hoje"
              label-calendar="Calendário"
              label-nav="Navegação do calendário"
              @input="onPickDate"
            >
              <template #button-content>
                <b-icon-calendar aria-hidden="true" />
                <span class="sr-only">Abrir calendário</span>
              </template>
            </b-form-datepicker>
          </div>
        </FormField>

        <FormField id="cf-time" label="Hora" :error="timeError">
          <b-form-input
            id="cf-time"
            v-model="form.time"
            type="time"
            required
            step="300"
            :disabled="locked"
            :state="timeError ? false : null"
            :aria-describedby="timeError ? 'cf-time-error' : undefined"
            @input="clearError('time', 'scheduled_at')"
          />
        </FormField>

        <FormField
          id="cf-duration"
          label="Duração"
          :error="errors.duration_minutes"
        >
          <b-form-select
            id="cf-duration"
            v-model="form.duration"
            required
            :options="durationOptions"
            :disabled="locked"
            :state="errors.duration_minutes ? false : null"
            :aria-describedby="errorId('duration_minutes', 'cf-duration')"
            @change="clearError('duration_minutes', 'scheduled_at')"
          />
        </FormField>
      </div>

      <div class="consultation-grid consultation-grid--who">
        <FormField
          id="cf-professional"
          label="Profissional"
          :error="errors.professional_name"
        >
          <b-form-input
            id="cf-professional"
            v-model="form.professional"
            required
            maxlength="255"
            placeholder="Nome do profissional"
            autocomplete="off"
            :disabled="locked"
            :state="errors.professional_name ? false : null"
            :aria-describedby="errorId('professional_name', 'cf-professional')"
            @input="clearError('professional_name', 'scheduled_at')"
          />
        </FormField>

        <FormField
          id="cf-crm"
          label="CRM"
          optional
          :error="errors.professional_crm"
        >
          <b-form-input
            id="cf-crm"
            v-model="form.crm"
            maxlength="20"
            placeholder="Ex.: 123456/SP"
            autocomplete="off"
            :disabled="locked"
            :state="errors.professional_crm ? false : null"
            :aria-describedby="errorId('professional_crm', 'cf-crm')"
            @input="clearError('professional_crm')"
          />
        </FormField>
      </div>

      <FormField
        id="cf-reason"
        label="Queixa principal"
        optional
        :error="errors.reason"
      >
        <b-form-textarea
          id="cf-reason"
          v-model="form.reason"
          rows="2"
          max-rows="8"
          maxlength="2000"
          placeholder="Descrever"
          :disabled="locked"
          :state="errors.reason ? false : null"
          :aria-describedby="errorId('reason', 'cf-reason')"
          @input="clearError('reason')"
        />
      </FormField>

      <FormField id="cf-notes" label="Anotações" optional :error="errors.notes">
        <b-form-textarea
          id="cf-notes"
          v-model="form.notes"
          rows="3"
          max-rows="12"
          maxlength="5000"
          placeholder="Descrever"
          :state="errors.notes ? false : null"
          :aria-describedby="errorId('notes', 'cf-notes')"
          @input="clearError('notes')"
        />
      </FormField>
    </div>

    <footer class="form-footer">
      <slot name="cancel">
        <button type="button" class="btn-link-danger" @click="$emit('cancel')">
          Cancelar
        </button>
      </slot>
      <b-button type="submit" variant="primary" :disabled="busy">
        <b-spinner v-if="busy" small class="mr-1" label="Salvando" />
        {{ submitLabel }}
      </b-button>
    </footer>
  </form>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue";
import type Vue from "vue";
import FormField from "@/components/FormField.vue";
import type {
  Consultation,
  ConsultationPayload,
} from "@/services/consultationService";
import type { ValidationErrors } from "@/services/api";
import { brToIso, isoToBr, isValidBrDate, maskDate } from "@/utils/format";
import { splitDateTime, todayInSaoPaulo } from "@/utils/datetime";
import { isFinalStatus } from "@/utils/consultationStatus";
import { notify } from "@/utils/notify";

export const DURATIONS = [15, 30, 45, 60, 90];
export const DEFAULT_DURATION = 30;
const REQUIRED = "Campo obrigatório";

interface FormState {
  date: string;
  time: string;
  duration: number;
  professional: string;
  crm: string;
  reason: string;
  notes: string;
}

/** Field keys: the API's plus the split date/time of `scheduled_at`. */
export type ConsultationFieldErrors = Partial<
  Record<keyof ConsultationPayload | "date" | "time", string>
>;

function toFormState(consultation: Consultation | null): FormState {
  if (!consultation) {
    return {
      date: isoToBr(todayInSaoPaulo()),
      time: "",
      duration: DEFAULT_DURATION,
      professional: "",
      crm: "",
      reason: "",
      notes: "",
    };
  }
  const { date, time } = splitDateTime(consultation.scheduled_at);
  return {
    date: isoToBr(date),
    time,
    duration: consultation.duration_minutes,
    professional: consultation.professional_name,
    crm: consultation.professional_crm ?? "",
    reason: consultation.reason ?? "",
    notes: consultation.notes ?? "",
  };
}

/** Client-side rules (the API validates again). */
export function validateConsultationForm(f: FormState): {
  errors: ConsultationFieldErrors;
  missingRequired: boolean;
} {
  const errors: ConsultationFieldErrors = {};
  let missingRequired = false;
  const required = (field: keyof ConsultationFieldErrors, value: string) => {
    if (value.trim()) return true;
    errors[field] = REQUIRED;
    missingRequired = true;
    return false;
  };
  if (required("date", f.date) && !isValidBrDate(f.date)) {
    errors.date = "Data inválida";
  }
  if (required("time", f.time) && !/^([01]\d|2[0-3]):[0-5]\d$/.test(f.time)) {
    errors.time = "Hora inválida";
  }
  if (!f.duration || f.duration < 10 || f.duration > 240) {
    errors.duration_minutes = "Duração inválida";
  }
  if (
    required("professional_name", f.professional) &&
    f.professional.trim().length < 3
  ) {
    errors.professional_name = "Informe ao menos 3 caracteres";
  }
  if (f.crm.trim().length > 20)
    errors.professional_crm = "Máximo de 20 caracteres";
  return { errors, missingRequired };
}

/**
 * Create / edit form of a consultation. In final states (completed, no-show,
 * cancelled) only the notes are editable and the other fields are sent back
 * exactly as the API returned them (any difference would be a 422).
 */
export default defineComponent({
  name: "ConsultationForm",
  components: { FormField },
  props: {
    title: { type: String, required: true },
    headingTag: { type: String, default: "h1" },
    headingClass: { type: String, default: "card-title-lg" },
    submitLabel: { type: String, required: true },
    consultation: {
      type: Object as PropType<Consultation | null>,
      default: null,
    },
    busy: { type: Boolean, default: false },
    serverErrors: {
      type: Object as PropType<ValidationErrors | null>,
      default: null,
    },
  },
  data() {
    return {
      form: toFormState(this.consultation) as FormState,
      errors: {} as ConsultationFieldErrors,
    };
  },
  computed: {
    locked(): boolean {
      return !!this.consultation && isFinalStatus(this.consultation.status);
    },
    dateIso(): string {
      return isValidBrDate(this.form.date) ? brToIso(this.form.date) : "";
    },
    /** Overlap / scheduling errors come on `scheduled_at`: shown under Hora. */
    timeError(): string {
      return this.errors.time || this.errors.scheduled_at || "";
    },
    dateDescribedBy(): string | undefined {
      if (this.errors.date) return "cf-date-error";
      if (this.errors.scheduled_at) return "cf-time-error";
      return undefined;
    },
    durationOptions(): { value: number; text: string }[] {
      const values = [...DURATIONS];
      // Durations set elsewhere (the API accepts 10–240) stay selectable.
      if (this.form.duration && !values.includes(this.form.duration)) {
        values.push(this.form.duration);
        values.sort((a, b) => a - b);
      }
      return values.map((value) => ({ value, text: `${value} minutos` }));
    },
    pickerButtonLabel(): string {
      return this.dateIso
        ? `Abrir calendário, data selecionada ${this.form.date}`
        : "Abrir calendário";
    },
  },
  watch: {
    pickerButtonLabel: "labelPickerButton",
    consultation(value: Consultation | null) {
      this.form = toFormState(value);
      this.errors = {};
    },
    serverErrors(value: ValidationErrors | null) {
      if (!value) return;
      const mapped: ConsultationFieldErrors = {};
      Object.entries(value).forEach(([field, messages]) => {
        if (messages?.length)
          mapped[field as keyof ConsultationFieldErrors] = messages[0];
      });
      this.errors = mapped;
      this.focusFirstError();
    },
  },
  mounted() {
    this.labelPickerButton();
  },
  methods: {
    maskDate,
    errorId(field: keyof ConsultationFieldErrors, id: string) {
      return this.errors[field] ? `${id}-error` : undefined;
    },
    /** Same fix as PatientForm: name the datepicker toggle by its own label. */
    labelPickerButton(): void {
      const picker = this.$refs.picker as Vue | undefined;
      const button = picker?.$el.querySelector("button");
      button?.setAttribute("aria-label", this.pickerButtonLabel);
    },
    clearError(...fields: (keyof ConsultationFieldErrors)[]): void {
      fields.forEach((field) => {
        if (this.errors[field]) this.$delete(this.errors, field);
      });
    },
    onPickDate(iso: string): void {
      if (!iso) return;
      this.form.date = isoToBr(iso);
      this.clearError("date", "scheduled_at");
    },
    toPayload(): ConsultationPayload {
      const f = this.form;
      const original = this.consultation;
      if (original && this.locked) {
        return {
          scheduled_at: original.scheduled_at,
          duration_minutes: original.duration_minutes,
          professional_name: original.professional_name,
          professional_crm: original.professional_crm,
          reason: original.reason,
          notes: f.notes.trim() || null,
        };
      }
      return {
        // No offset: the API reads it as America/Sao_Paulo.
        scheduled_at: `${brToIso(f.date)}T${f.time}`,
        duration_minutes: Number(f.duration),
        professional_name: f.professional.trim(),
        professional_crm: f.crm.trim() || null,
        reason: f.reason.trim() || null,
        notes: f.notes.trim() || null,
      };
    },
    submit(): void {
      if (this.busy) return;
      const { errors, missingRequired } = this.locked
        ? { errors: {}, missingRequired: false }
        : validateConsultationForm(this.form);
      this.errors = errors;
      if (Object.keys(errors).length) {
        notify(
          this.$root,
          "error",
          missingRequired
            ? "Preencha os campos obrigatórios para salvar!"
            : "Verifique os campos destacados."
        );
        this.focusFirstError();
        return;
      }
      this.$emit("submit", this.toPayload());
    },
    focusFirstError(): void {
      this.$nextTick(() => {
        const el = this.$el.querySelector(".is-invalid") as HTMLElement | null;
        el?.focus();
      });
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.consultation-form {
  display: flex;
  flex-direction: column;
}

.consultation-form__notice {
  margin: 16px 0 0;
  padding: 10px 14px;
  color: $navy;
  font-size: 14px;
  background: $page-bg;
  border-left: 3px solid $blue;
  border-radius: $border-radius;
}

.consultation-form__body {
  padding-top: 32px;
}

.consultation-grid {
  display: grid;
  column-gap: 8px;
}

.consultation-grid--when {
  grid-template-columns: 200px 160px 200px;
}

.consultation-grid--who {
  grid-template-columns: minmax(0, 1fr) 200px;
}

@media (max-width: 767.98px) {
  .consultation-grid--when {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 575.98px) {
  .consultation-grid--when,
  .consultation-grid--who {
    grid-template-columns: minmax(0, 1fr);
  }
}

.date-input {
  position: relative;

  ::v-deep .form-control {
    padding-right: 44px;
  }
}

.date-input__picker {
  position: absolute;
  top: 0;
  right: 0;
  height: 40px;

  ::v-deep .btn {
    display: flex;
    align-items: center;
    height: 40px;
    padding: 0 14px;
    color: $text-muted;
    font-size: 20px;
    border: 0;
    box-shadow: none;

    &:hover,
    &:focus {
      color: $blue;
    }
  }
}

.form-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding-top: 24px;
}
</style>
