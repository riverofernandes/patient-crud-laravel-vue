<template>
  <section class="address-fields" aria-labelledby="pf-address-title">
    <header class="address-fields__header">
      <div>
        <h2 id="pf-address-title" class="address-fields__title">
          Endereço
          <small class="address-fields__optional">(Opcional)</small>
        </h2>
        <p id="pf-address-hint" class="address-fields__hint">
          Ao informar o endereço, só o complemento pode ficar em branco.
        </p>
      </div>
      <button
        v-if="removable && !empty"
        type="button"
        class="btn-link-danger address-fields__remove"
        @click="remove"
      >
        <b-icon-trash aria-hidden="true" class="mr-1" />
        Remover endereço
      </button>
    </header>

    <div class="address-grid address-grid--row1">
      <FormField id="pf-address-cep" label="CEP" :error="cepError">
        <div class="cep-input">
          <b-form-input
            id="pf-address-cep"
            ref="cep"
            :value="value.cep"
            placeholder="00000-000"
            inputmode="numeric"
            :maxlength="max.cep"
            autocomplete="postal-code"
            :formatter="maskCep"
            :state="cepError ? false : null"
            :aria-describedby="cepDescribedBy"
            :aria-busy="looking ? 'true' : null"
            @input="onCepInput"
            @blur="onCepBlur"
          />
          <b-spinner
            v-if="looking"
            small
            class="cep-input__spinner"
            aria-hidden="true"
          />
        </div>
        <p v-if="cepNote" id="pf-address-cep-note" class="address-fields__note">
          {{ cepNote }}
        </p>
      </FormField>

      <FormField id="pf-address-street" label="Rua" :error="errors.street">
        <b-form-input
          id="pf-address-street"
          :value="value.street"
          placeholder="Descrever"
          :maxlength="max.street"
          autocomplete="address-line1"
          :state="state('street')"
          :aria-describedby="errorId('street')"
          @input="set('street', $event)"
        />
      </FormField>

      <FormField id="pf-address-number" label="Número" :error="errors.number">
        <b-form-input
          id="pf-address-number"
          ref="number"
          :value="value.number"
          placeholder="Ex.: 123"
          :maxlength="max.number"
          :state="state('number')"
          :aria-describedby="errorId('number')"
          @input="set('number', $event)"
        />
      </FormField>
    </div>

    <div class="address-grid address-grid--row2">
      <FormField
        id="pf-address-complement"
        label="Complemento"
        optional
        :error="errors.complement"
      >
        <b-form-input
          id="pf-address-complement"
          :value="value.complement"
          placeholder="Descrever"
          :maxlength="max.complement"
          autocomplete="address-line2"
          :state="state('complement')"
          :aria-describedby="errorId('complement')"
          @input="set('complement', $event)"
        />
      </FormField>

      <FormField
        id="pf-address-neighborhood"
        label="Bairro"
        :error="errors.neighborhood"
      >
        <b-form-input
          id="pf-address-neighborhood"
          :value="value.neighborhood"
          placeholder="Descrever"
          :maxlength="max.neighborhood"
          :state="state('neighborhood')"
          :aria-describedby="errorId('neighborhood')"
          @input="set('neighborhood', $event)"
        />
      </FormField>

      <FormField id="pf-address-city" label="Cidade" :error="errors.city">
        <b-form-input
          id="pf-address-city"
          :value="value.city"
          placeholder="Descrever"
          :maxlength="max.city"
          autocomplete="address-level2"
          :state="state('city')"
          :aria-describedby="errorId('city')"
          @input="set('city', $event)"
        />
      </FormField>

      <FormField id="pf-address-state" label="UF" :error="errors.state">
        <b-form-select
          id="pf-address-state"
          :value="value.state"
          :options="ufOptions"
          autocomplete="address-level1"
          :class="{ 'is-placeholder': !value.state }"
          :state="state('state')"
          :aria-describedby="errorId('state')"
          @change="set('state', $event)"
        />
      </FormField>
    </div>

    <!-- CEP lookup results and the removal are announced politely. -->
    <p class="sr-only" role="status" aria-live="polite">{{ announcement }}</p>
  </section>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue";
import axios from "axios";
import FormField from "@/components/FormField.vue";
import { cepService } from "@/services/cepService";
import { getStatus } from "@/services/api";
import {
  ADDRESS_MAX,
  UFS,
  isAddressEmpty,
  maskCep,
  toAddressForm,
  type AddressErrors,
  type AddressField,
  type AddressForm,
} from "@/utils/address";
import { onlyDigits } from "@/utils/format";

export const CEP_FOUND = "Endereço encontrado";
export const CEP_NOT_FOUND = "CEP não encontrado";
export const CEP_UNAVAILABLE =
  "Serviço de CEP indisponível. Preencha o endereço manualmente.";
export const ADDRESS_REMOVED =
  "Endereço removido. Salve para confirmar a remoção.";

/**
 * Address section of the patient form. The parent owns the values (v-model)
 * and the validation errors; this component owns the CEP lookup.
 */
export default defineComponent({
  name: "AddressFields",
  components: { FormField },
  props: {
    value: { type: Object as PropType<AddressForm>, required: true },
    errors: { type: Object as PropType<AddressErrors>, default: () => ({}) },
    /** Shows "Remover endereço" (edit form). */
    removable: { type: Boolean, default: false },
  },
  data() {
    return {
      max: ADDRESS_MAX,
      ufOptions: [
        { value: "", text: "Selecionar" },
        ...UFS.map((uf) => ({ value: uf.value, text: uf.value })),
      ],
      looking: false,
      /** CEP (digits) already looked up or loaded, so blur doesn't repeat it. */
      lastLookup: onlyDigits(this.value.cep),
      lookupError: "",
      cepNote: "",
      announcement: "",
      abort: null as AbortController | null,
    };
  },
  computed: {
    empty(): boolean {
      return isAddressEmpty(this.value);
    },
    cepError(): string {
      return this.errors.cep || this.lookupError;
    },
    cepDescribedBy(): string | undefined {
      const ids = [
        this.cepError ? "pf-address-cep-error" : "",
        this.cepNote ? "pf-address-cep-note" : "",
      ].filter(Boolean);
      return ids.length ? ids.join(" ") : undefined;
    },
  },
  watch: {
    /**
     * A complete CEP that arrives from outside (patient loaded into the form)
     * counts as already looked up; typing goes through lookup() first anyway.
     */
    "value.cep"(cep: string) {
      const digits = onlyDigits(cep);
      if (digits.length < 8) this.lastLookup = "";
      else if (!this.abort && !this.cepNote) this.lastLookup = digits;
    },
  },
  beforeDestroy() {
    this.cancelLookup();
  },
  methods: {
    maskCep,
    state(field: AddressField): false | null {
      return this.errors[field] ? false : null;
    },
    errorId(field: AddressField): string | undefined {
      return this.errors[field] ? `pf-address-${field}-error` : undefined;
    },
    set(field: AddressField, value: string): void {
      this.$emit("input", { ...this.value, [field]: value });
      if (this.errors[field]) this.$emit("clear-error", field);
    },
    onCepInput(value: string): void {
      this.set("cep", value);
      this.lookupError = "";
      this.cepNote = "";
      this.announcement = "";
      const digits = onlyDigits(value);
      if (digits.length < 8) {
        this.cancelLookup();
        this.lastLookup = "";
        return;
      }
      void this.lookup(digits);
    },
    onCepBlur(): void {
      const digits = onlyDigits(this.value.cep);
      if (digits.length === 8) void this.lookup(digits);
    },
    cancelLookup(): void {
      this.abort?.abort();
      this.abort = null;
      this.looking = false;
    },
    async lookup(cep: string): Promise<void> {
      if (cep === this.lastLookup) return;
      this.cancelLookup();
      this.lastLookup = cep;
      const controller = new AbortController();
      this.abort = controller;
      this.looking = true;
      try {
        const result = await cepService.lookup(cep, {
          signal: controller.signal,
        });
        if (this.isStale(controller, cep)) return;
        const filled: Partial<AddressForm> = {};
        if (result.street) filled.street = result.street;
        if (result.neighborhood) filled.neighborhood = result.neighborhood;
        if (result.city) filled.city = result.city;
        if (result.state) filled.state = result.state.toUpperCase();
        this.$emit("input", { ...this.value, ...filled });
        (Object.keys(filled) as AddressField[]).forEach((field) => {
          if (this.errors[field]) this.$emit("clear-error", field);
        });
        this.announcement = CEP_FOUND;
        this.$nextTick(() => this.focusRef("number"));
      } catch (error) {
        if (axios.isCancel(error) || this.isStale(controller, cep)) return;
        const status = getStatus(error);
        if (status === 404 || status === 422) {
          this.lookupError = status === 404 ? CEP_NOT_FOUND : "CEP inválido";
          this.announcement = this.lookupError;
        } else {
          // 503 or network failure: never blocks, the address is typed by hand.
          this.cepNote = CEP_UNAVAILABLE;
          this.announcement = CEP_UNAVAILABLE;
          this.lastLookup = "";
        }
      } finally {
        if (this.abort === controller) {
          this.abort = null;
          this.looking = false;
        }
      }
    },
    /** The user changed the CEP (or a newer lookup started) meanwhile. */
    isStale(controller: AbortController, cep: string): boolean {
      return (
        controller.signal.aborted ||
        this.abort !== controller ||
        onlyDigits(this.value.cep) !== cep
      );
    },
    remove(): void {
      this.cancelLookup();
      this.lastLookup = "";
      this.lookupError = "";
      this.cepNote = "";
      this.$emit("input", toAddressForm(null));
      this.$emit("removed");
      this.announcement = ADDRESS_REMOVED;
      this.$nextTick(() => this.focusRef("cep"));
    },
    focusRef(name: "cep" | "number"): void {
      const ref = this.$refs[name] as { focus?: () => void } | undefined;
      ref?.focus?.();
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.address-fields {
  margin-top: 8px;
  padding-top: 24px;
  border-top: 2px solid $border-light;
}

.address-fields__header {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 16px;
}

.address-fields__title {
  margin: 0 0 4px;
  color: $navy;
  font-size: 18px;
  font-weight: 700;
  line-height: 24px;
}

.address-fields__optional {
  margin-left: 4px;
  color: $text-muted;
  font-size: 12px;
  font-weight: 400;
}

.address-fields__hint {
  margin: 0;
  color: $text-muted;
  font-size: 14px;
}

.address-fields__remove {
  display: inline-flex;
  align-items: center;
}

.address-fields__note {
  margin: 4px 0 0;
  color: $text-muted;
  font-size: 12px;
  line-height: 16px;
}

.cep-input {
  position: relative;
}

.cep-input__spinner {
  position: absolute;
  top: 12px;
  right: 12px;
  color: $blue;
}

.address-grid {
  display: grid;
  column-gap: 8px;
}

.address-grid--row1 {
  grid-template-columns: 160px minmax(0, 1fr) 160px;
}

.address-grid--row2 {
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) 160px;
}

@media (max-width: 1099.98px) {
  .address-grid--row1,
  .address-grid--row2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .address-grid--row1 > :nth-child(2) {
    grid-column: 1 / -1;
    grid-row: 2;
  }
}

@media (max-width: 575.98px) {
  .address-grid--row1,
  .address-grid--row2 {
    grid-template-columns: minmax(0, 1fr);
  }

  .address-grid--row1 > :nth-child(2) {
    grid-row: auto;
  }
}
</style>
