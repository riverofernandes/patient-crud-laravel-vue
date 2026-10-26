<template>
  <fieldset class="patient-filters">
    <legend class="sr-only">Filtros</legend>

    <div class="patient-filters__fields">
      <FormField id="filter-sex" label="Sexo" class="patient-filters__sex">
        <b-form-select
          id="filter-sex"
          ref="sex"
          v-model="sex"
          :options="sexOptions"
          @change="apply"
        />
      </FormField>

      <FormField
        id="filter-age-min"
        label="Idade mínima"
        :error="errors.ageMin"
        class="patient-filters__age"
      >
        <b-form-input
          id="filter-age-min"
          ref="ageMin"
          v-model="ageMin"
          type="number"
          inputmode="numeric"
          :min="limits.min"
          :max="limits.max"
          step="1"
          :state="errors.ageMin ? false : null"
          :aria-invalid="errors.ageMin ? 'true' : null"
          :aria-describedby="errors.ageMin ? 'filter-age-min-error' : null"
          @change="apply"
          @keydown.enter.prevent="apply"
        />
      </FormField>

      <FormField
        id="filter-age-max"
        label="Idade máxima"
        :error="errors.ageMax"
        class="patient-filters__age"
      >
        <b-form-input
          id="filter-age-max"
          ref="ageMax"
          v-model="ageMax"
          type="number"
          inputmode="numeric"
          :min="limits.min"
          :max="limits.max"
          step="1"
          :state="errors.ageMax ? false : null"
          :aria-invalid="errors.ageMax ? 'true' : null"
          :aria-describedby="errors.ageMax ? 'filter-age-max-error' : null"
          @change="apply"
          @keydown.enter.prevent="apply"
        />
      </FormField>

      <div class="patient-filters__actions">
        <button
          type="button"
          class="btn btn-link patient-filters__clear"
          :aria-disabled="canClear ? undefined : 'true'"
          :class="{ 'is-disabled': !canClear }"
          @click="clear"
        >
          <b-icon-x aria-hidden="true" />
          Limpar filtros
        </button>
      </div>
    </div>
  </fieldset>
</template>

<script lang="ts">
import { defineComponent, type PropType } from "vue";
import FormField from "@/components/FormField.vue";
import {
  AGE_MAX_LIMIT,
  AGE_MIN_LIMIT,
  AGE_RANGE_MESSAGE,
  EMPTY_FILTERS,
  activeFilterCount,
  parseAge,
  parseSex,
  sameFilters,
  validateAgeRange,
  type PatientFilterValues,
} from "@/utils/filters";

const toText = (age: number | null) => (age === null ? "" : String(age));

/**
 * Sex / age-range filters. Emits `change` with valid values only (on select
 * change, and on blur/Enter for the ages, so typing doesn't spam history);
 * invalid input stays local with an inline error.
 */
export default defineComponent({
  name: "PatientFilters",
  components: { FormField },
  props: {
    /** Currently applied filters (from the URL). */
    value: {
      type: Object as PropType<PatientFilterValues>,
      default: () => ({ ...EMPTY_FILTERS }),
    },
  },
  data() {
    return {
      sex: "" as string,
      ageMin: "",
      ageMax: "",
      errors: { ageMin: "", ageMax: "" },
      limits: { min: AGE_MIN_LIMIT, max: AGE_MAX_LIMIT },
      sexOptions: [
        { value: "", text: "Todos" },
        { value: "M", text: "Masculino" },
        { value: "F", text: "Feminino" },
      ],
    };
  },
  computed: {
    canClear(): boolean {
      return (
        activeFilterCount(this.value) > 0 ||
        this.sex !== "" ||
        this.ageMin !== "" ||
        this.ageMax !== ""
      );
    },
  },
  watch: {
    value: {
      immediate: true,
      handler(value: PatientFilterValues, old?: PatientFilterValues) {
        // Unrelated query changes (page, sort...) keep what is being typed.
        if (old && sameFilters(value, old)) return;
        this.sex = value.sex;
        this.ageMin = toText(value.ageMin);
        this.ageMax = toText(value.ageMax);
        this.errors = validateAgeRange(this.ageMin, this.ageMax);
      },
    },
  },
  methods: {
    /** Text typed into a number input the browser can't parse (e.g. "1e"). */
    hasBadInput(ref: "ageMin" | "ageMax"): boolean {
      const component = this.$refs[ref] as { $el?: HTMLInputElement };
      return !!component?.$el?.validity?.badInput;
    },
    apply(): void {
      const errors = validateAgeRange(String(this.ageMin), String(this.ageMax));
      if (!errors.ageMin && this.hasBadInput("ageMin"))
        errors.ageMin = AGE_RANGE_MESSAGE;
      if (!errors.ageMax && this.hasBadInput("ageMax"))
        errors.ageMax = AGE_RANGE_MESSAGE;
      this.errors = errors;
      if (errors.ageMin || errors.ageMax) return;
      const next: PatientFilterValues = {
        sex: parseSex(this.sex),
        ageMin: parseAge(this.ageMin),
        ageMax: parseAge(this.ageMax),
      };
      if (!sameFilters(next, this.value)) this.$emit("change", next);
    },
    clear(): void {
      if (!this.canClear) return;
      this.sex = "";
      this.ageMin = "";
      this.ageMax = "";
      this.errors = { ageMin: "", ageMax: "" };
      if (activeFilterCount(this.value) > 0)
        this.$emit("change", { ...EMPTY_FILTERS });
      // The button becomes inactive: keep focus inside the panel.
      this.$nextTick(() => {
        const sex = this.$refs.sex as { $el?: HTMLElement } | undefined;
        sex?.$el?.focus();
      });
    },
  },
});
</script>

<style lang="scss" scoped>
@import "@/styles/variables";

.patient-filters {
  min-width: 0;
  margin: 0 0 8px;
  padding: 16px 16px 0;
  background: $page-bg;
  border: 1px solid $border-light;
  border-radius: $border-radius-lg;
}

.patient-filters__fields {
  display: grid;
  grid-template-columns: 200px 140px 140px auto;
  gap: 0 16px;
  align-items: start;
}

.patient-filters ::v-deep .form-field__label {
  font-size: 14px;
}

.patient-filters ::v-deep .form-field__feedback {
  text-align: left;
}

.patient-filters ::v-deep .custom-select,
.patient-filters ::v-deep .form-control {
  background-color: #fff;
}

.patient-filters ::v-deep .form-control.is-invalid {
  border-color: $red;
}

.patient-filters__actions {
  display: flex;
  align-items: flex-end;
  // Aligns the button with the inputs (label height + margin).
  padding-top: 28px;
}

.patient-filters__clear {
  display: inline-flex;
  gap: 4px;
  align-items: center;
  height: 40px;
  padding: 0 4px;
  font-size: 14px;
  font-weight: 700;

  &.is-disabled {
    color: $text-muted;
    text-decoration: none;
    cursor: default;
  }
}

@media (max-width: 767.98px) {
  .patient-filters__fields {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .patient-filters__sex,
  .patient-filters__actions {
    grid-column: 1 / -1;
  }

  .patient-filters__actions {
    padding-top: 0;
    padding-bottom: 8px;
  }
}
</style>
