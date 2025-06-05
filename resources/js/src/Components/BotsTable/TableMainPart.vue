<script setup>
import { defineEmits, defineProps } from "vue";
import TableItem from "@/Components/BotsTable/TableItem.vue";
import TableItemCheckBox from "@/Components/BotsTable/TableItemCheckBox.vue";
import TableItemLink from "@/Components/BotsTable/TableItemLink.vue";
import TableItemDeleteButton from "@/Components/BotsTable/TableItemDeleteButton.vue";
import TableItemLinkArray from "@/Components/BotsTable/TableItemLinkArray.vue";

const props = defineProps({
    columns: {
        type: Array,
        required: true,
    },
    data: {
        type: Array,
        required: true,
    },
});

const componentMap = {
    default: TableItem,
    checkbox: TableItemCheckBox,
    link: TableItemLink,
    button: TableItemDeleteButton,
    arrayLink: TableItemLinkArray,
};

function getComponentType(type) {
    return componentMap[type] || componentMap["default"];
}

const emit = defineEmits(["handle"]);

function handleEvent(event) {
    emit("handle", event);
}
</script>

<template>
    <div class="col-sm-12">
        <div class="table-wrapper">
            <table class="table table-hover text-nowrap">
                <thead>
                    <tr>
                        <th v-for="column in columns" :key="column.key">
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in data" :key="item.id">
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="bot-table-td"
                        >
                            <component
                                :is="getComponentType(column.type)"
                                :data="item[column.key]"
                                :limit="column.limit"
                                :action="column.action"
                                :id="item.id"
                                @handle="handleEvent"
                            >
                            </component>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
.table-wrapper {
    overflow-x: auto;
}

.bot-table-td {
    /* text-align: center; */
}
</style>
