<template>
  <form class="app-card patient-form" novalidate @submit.prevent="submit">
    <h1 class="card-title-lg">{{ title }}</h1>

    <div class="patient-form__body">
      <div class="patient-grid patient-grid--row1">
        <FormField id="pf-name" label="Nome completo" :error="errors.name">
          <b-form-input
            id="pf-name"
            required
            v-model="form.name"
            placeholder="Descrever"
            maxlength="255"
            autocomplete="name"
            :state="state('name')"
            :aria-describedby="errorId('name')"
            @input="clearError('name')"
          />
        </FormField>

        <FormField id="pf-cpf" label="CPF" :error="errors.cpf">
          <b-form-input
            id="pf-cpf"
            required
            v-model="form.cpf"
            placeholder="000.000.000-00"
            inputmode="numeric"
            maxlength="14"
            :formatter="maskCpf"
            :state="state('cpf')"
            :aria-describedby="errorId('cpf')"
            @input="clearError('cpf')"
          />
        </FormField>

        <FormField id="pf-rg" label="RG" :error="errors.rg">
          <b-form-input
            id="pf-rg"
            required
            v-model="form.rg"
            placeholder="00.000.000-0"
            maxlength="20"
            :formatter="maskRg"
            :state="state('rg')"
            :aria-describedby="errorId('rg')"
            @input="clearError('rg')"
          />
        </FormField>
      </div>

      <div class="patient-grid patient-grid--row2">
        <FormField
          id="pf-birth-date"
          label="Data de nascimento"
          :error="errors.birth_date"
        >
          <div class="date-input">
            <b-form-input
              id="pf-birth-date"
              required
              v-model="form.birthDate"
              placeholder="DD/MM/AAAA"
              inputmode="numeric"
              maxlength="10"
              autocomplete="bday"
              :formatter="maskDate"
              :state="state('birth_date')"
              :aria-describedby="errorId('birth_date')"
              @input="clearError('birth_date')"
            />
            <b-form-datepicker
              ref="picker"
              :value="birthDateIso"
              class="date-input__picker"
              button-only
              right
              locale="pt-BR"
              :max="today"
              :start-weekday="0"
              :show-decade-nav="true"
              button-variant="link"
              label-help="Use as setas do teclado para navegar"
              label-no-date-selected="Nenhuma data selecionada"
              label-selected="Data selecionada"
              label-prev-year="Ano anterior"
              label-prev-month="Mês anterior"
              label-current-month="Mês atual"
              label-next-month="Próximo mês"
              label-next-year="Próximo ano"
              label-prev-decade="Década anterior"
              label-next-decade="Próxima década"
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

        <FormField id="pf-sex" label="Sexo biológico" :error="errors.sex">
          <b-form-select
            id="pf-sex"
            required
            v-model="form.sex"
            :options="sexOptions"
            :class="{ 'is-placeholder': !form.sex }"
            :state="state('sex')"
            :aria-describedby="errorId('sex')"
            @change="clearError('sex')"
          />
        </FormField>

        <FormField id="pf-phone" label="Celular" :error="errors.phone">
          <b-form-input
            id="pf-phone"
            required
            v-model="form.phone"
            type="tel"
            placeholder="(00) 00000-0000"
            inputmode="numeric"
            maxlength="15"
            autocomplete="tel-national"
            :formatter="maskPhone"
            :state="state('phone')"
            :aria-describedby="errorId('phone')"
            @input="clearError('phone')"
          />
        </FormField>

        <FormField id="pf-email" label="E-mail" optional :error="errors.email">
          <b-form-input
            id="pf-email"
            v-model="form.email"
            type="email"
            placeholder="Descrever"
            maxlength="255"
            autocomplete="email"
            :state="state('email')"
            :aria-describedby="errorId('email')"
            @input="clearError('email')"
          />
        </FormField>
      </div>

      <FormField
        id="pf-description"
        label="Descrição"
        optional
        :error="errors.description"
      >
        <b-form-textarea
          id="pf-description"
          v-model="form.description"
          placeholder="Descrever"
          rows="1"
          max-rows="8"
          maxlength="2000"
          :state="state('description')"
          :aria-describedby="errorId('description')"
          @input="clearError('description')"
        />
      </FormField>
    </div>

    <footer class="form-footer">
      <slot name="footer-start">
        <router-link :to="{ name: 'patients.index' }" class="btn-link-danger"
          >Cancelar</router-link
        >
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
import type { Patient, PatientPayload, Sex } from "@/services/patientService";
import type { ValidationErrors } from "@/services/api";
import {
  brToIso,
  isFutureBrDate,
  isoToBr,
  isValidBrDate,
  maskCpf,
  maskDate,
  maskPhone,
  maskRg,
  normalizeRg,
  onlyDigits,
  todayIso,
} from "@/utils/format";
import { isValidCpf, isValidEmail } from "@/utils/validation";
import { notify } from "@/utils/notify";

type FieldName = keyof PatientPayload;
type FieldErrors = Partial<Record<FieldName, string>>;

interface FormState {
  name: string;
  cpf: string;
  rg: string;
  birthDate: string;
  sex: Sex | "";
  phone: string;
  email: string;
  description: string;
}

const REQUIRED = "Campo obrigatório";

function toFormState(patient: Patient | null): FormState {
  return {
    name: patient?.name ?? "",
    cpf: maskCpf(patient?.cpf),
    rg: maskRg(patient?.rg),
    birthDate: isoToBr(patient?.birth_date),
    sex: patient?.sex ?? "",
    phone: maskPhone(patient?.phone),
    email: patient?.email ?? "",
    description: patient?.description ?? "",
  };
}

export default defineComponent({
  name: "PatientForm",
  components: { FormField },
  props: {
    title: { type: String, required: true },
    submitLabel: { type: String, required: true },
    patient: { type: Object as PropType<Patient | null>, default: null },
    busy: { type: Boolean, default: false },
    /** Laravel 422 `errors` bag returned by the API. */
    serverErrors: {
      type: Object as PropType<ValidationErrors | null>,
      default: null,
    },
  },
  data() {
    return {
      form: toFormState(this.patient) as FormState,
      errors: {} as FieldErrors,
      today: todayIso(),
      sexOptions: [
        { value: "", text: "Selecionar", disabled: true },
        { value: "M", text: "Masculino" },
        { value: "F", text: "Feminino" },
      ],
    };
  },
  computed: {
    pickerButtonLabel(): string {
      return this.birthDateIso
        ? `Abrir calendário, data selecionada ${this.form.birthDate}`
        : "Abrir calendário";
    },
    birthDateIso(): string {
      return isValidBrDate(this.form.birthDate)
        ? brToIso(this.form.birthDate)
        : "";
    },
  },
  watch: {
    pickerButtonLabel: "labelPickerButton",
    patient(value: Patient | null) {
      this.form = toFormState(value);
      this.errors = {};
    },
    serverErrors(value: ValidationErrors | null) {
      if (!value) return;
      const mapped: FieldErrors = {};
      Object.entries(value).forEach(([field, messages]) => {
        if (messages?.length) mapped[field as FieldName] = messages[0];
      });
      this.errors = mapped;
      this.focusFirstError();
    },
  },
  mounted() {
    this.labelPickerButton();
  },
  methods: {
    maskCpf,
    maskRg,
    maskPhone,
    maskDate,
    state(field: FieldName): false | null {
      return this.errors[field] ? false : null;
    },
    errorId(field: FieldName): string | undefined {
      return this.errors[field]
        ? `pf-${field.replace("_", "-")}-error`
        : undefined;
    },
    /**
     * BootstrapVue names the datepicker toggle through a hidden <label for=button>
     * holding the selected date or "Nenhuma data selecionada", so screen readers
     * announced the button as that text. An aria-label on the button itself wins
     * in the accessible-name computation (and Vue leaves unmanaged attrs alone).
     */
    labelPickerButton(): void {
      const picker = this.$refs.picker as Vue | undefined;
      const button = picker?.$el.querySelector("button");
      button?.setAttribute("aria-label", this.pickerButtonLabel);
    },
    clearError(field: FieldName): void {
      if (this.errors[field]) this.$delete(this.errors, field);
    },
    onPickDate(iso: string): void {
      if (!iso) return;
      this.form.birthDate = isoToBr(iso);
      this.clearError("birth_date");
    },
    validate(): { errors: FieldErrors; missingRequired: boolean } {
      const f = this.form;
      const errors: FieldErrors = {};
      let missingRequired = false;
      const required = (field: FieldName, value: string): boolean => {
        if (value.trim()) return true;
        errors[field] = REQUIRED;
        missingRequired = true;
        return false;
      };

      if (required("name", f.name) && f.name.trim().length < 3) {
        errors.name = "Informe ao menos 3 caracteres";
      }
      if (required("cpf", f.cpf) && !isValidCpf(f.cpf)) {
        errors.cpf = "CPF inválido";
      }
      required("rg", f.rg);
      if (required("birth_date", f.birthDate)) {
        if (!isValidBrDate(f.birthDate)) errors.birth_date = "Data inválida";
        else if (isFutureBrDate(f.birthDate))
          errors.birth_date = "Data não pode ser futura";
      }
      required("sex", f.sex);
      if (required("phone", f.phone)) {
        const len = onlyDigits(f.phone).length;
        if (len < 10 || len > 11) errors.phone = "Celular inválido";
      }
      if (f.email.trim() && !isValidEmail(f.email)) {
        errors.email = "E-mail inválido";
      }
      return { errors, missingRequired };
    },
    toPayload(): PatientPayload {
      const f = this.form;
      return {
        name: f.name.trim(),
        cpf: onlyDigits(f.cpf),
        rg: normalizeRg(f.rg),
        birth_date: brToIso(f.birthDate),
        sex: f.sex as Sex,
        phone: onlyDigits(f.phone),
        email: f.email.trim() || null,
        description: f.description.trim() || null,
      };
    },
    submit(): void {
      if (this.busy) return;
      const { errors, missingRequired } = this.validate();
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

.patient-form {
  display: flex;
  flex-direction: column;
  min-height: calc(100vh - 190px);
}

.patient-form__body {
  padding-top: 32px;
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
  padding-top: 32px;
}

@media (max-width: 767.98px) {
  .patient-form {
    min-height: 0;
  }
}
</style>
