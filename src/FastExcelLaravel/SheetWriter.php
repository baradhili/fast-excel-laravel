<?php

namespace avadim\FastExcelLaravel;

use avadim\FastExcelWriter\Sheet;
use avadim\FastExcelWriter\Style;
use Illuminate\Support\Collection;

class SheetWriter extends Sheet
{
    /** @var mixed|null  */
    private $mappingCallback = null;

    private array $headers = [];
    private array $attrFormats = [];
    private int $dataRowCount = 0;


    /**
     * @param $record
     *
     * @return array
     */
    protected function _toArray($record): array
    {
        if (is_object($record)) {
            if (method_exists($record, 'toArray')) {
                $result = $record->toArray();
            }
            else {
                $result = json_decode(json_encode($record), true);
            }
        }
        else {
            $result = (array)$record;
        }

        return $result;
    }

    /**
     * @param $record
     *
     * @return void
     */
    protected function _writeHeader($record)
    {
        if (!$this->headers['header_keys']) {
            $this->headers['header_keys'] = array_keys($this->_toArray($record));
        }
        if (!$this->headers['header_values']) {
            $this->headers['header_values'] = $this->headers['header_keys'];
        }

        //$row = array_combine($this->headers['header_keys'], $this->headers['header_values']);
        $row = $this->headers['header_values'];
        $this->writeHeader($row, $this->headers['row_style'], $this->headers['col_styles']);
        ++$this->dataRowCount;
    }

    /**
     * @param array $rowValues
     * @param array|null $rowStyle
     * @param array|null $cellStyles
     *
     * @return SheetWriter
     */
    public function writeRow(array $rowValues = [], array $rowStyle = null, array $cellStyles = null): SheetWriter
    {
        if ($this->dataRowCount > 0 && !empty($this->headers['header_keys'])) {
            $rowData = [];
            foreach ($this->headers['header_keys'] as $key) {
                if (isset($rowValues[$key])) {
                    $rowData[$key] = $rowValues[$key];
                }
                else {
                    $rowData[] = null;
                }
            }
        }
        else {
            $rowData = $rowValues;
        }
        if ($this->attrFormats) {
            $cellStyles = (array)$cellStyles;
            foreach (array_keys($rowData) as $n => $attribute) {
                if (isset($this->attrFormats[$attribute])) {
                    $cellStyles[$n]['format'] = $this->attrFormats[$attribute];
                }
            }
        }

        return parent::writeRow($rowData, $rowStyle, $cellStyles);
    }

    /**
     * @param $data
     * @param array|null $rowStyle
     * @param array|null $colStyles
     *
     * @return $this
     */
    public function writeData($data, array $rowStyle = null, array $colStyles = null): SheetWriter
    {
        if (is_array($data) || ($data instanceof Collection)) {
            foreach ($data as $record) {
                if ($this->dataRowCount === 0 && $this->headers) {
                    $this->_writeHeader($record);
                }
                if ($this->mappingCallback) {
                    $record = call_user_func($this->mappingCallback, $record);
                }
                $this->writeRow($this->_toArray($record), $rowStyle, $colStyles);
                ++$this->dataRowCount;
            }
        }
        elseif (is_callable($data)) {
            foreach ($data() as $record) {
                if ($this->dataRowCount === 0 && $this->headers) {
                    $this->_writeHeader($record);
                }
                if ($this->mappingCallback) {
                    $record = call_user_func($this->mappingCallback, $record);
                }
                $this->writeRow($this->_toArray($record), $rowStyle, $colStyles);
                ++$this->dataRowCount;
            }
        }

        return $this;
    }

    /**
     * @param $model
     * @param array|null $rowStyle
     * @param array|null $colStyles
     *
     * @return $this
     */
    public function exportModel($model, array $rowStyle = null, array $colStyles = null): SheetWriter
    {
        $this->writeData(static function() use ($model) {
            foreach ($model::cursor() as $user) {
                yield $user;
            }
        }, $rowStyle, $colStyles);
        $this->headers = [];

        return $this;
    }

    /**
     * @param array|null $headers
     * @param array|null $rowStyle
     * @param array|null $colStyles
     *
     * @return $this
     */
    public function withHeadings(?array $headers = [], ?array $rowStyle = [], ?array $colStyles = []): SheetWriter
    {
        $headerKeys = $headerValues = [];
        if ($headers) {
            foreach ($headers as $key => $val) {
                if (is_string($key)) {
                    $headerKeys[] = $key;
                    $headerValues[] = $val;
                }
                else {
                    $headerKeys[] = $headerValues[] = $val;
                }
            }
        }

        $this->headers = [
            'header_keys' => $headerKeys,
            'header_values' => $headerValues,
            'row_style' => $rowStyle,
            'col_styles' => $colStyles,
        ];
        $this->lastTouch['ref'] = 'row';

        return $this;
    }

    /**
     * @param $callback
     *
     * @return $this
     */
    public function mapping($callback): SheetWriter
    {
        $this->mappingCallback = $callback;

        return $this;
    }

    /**
     * @param array $formats
     *
     * @return $this
     */
    public function formatAttributes(array $formats): SheetWriter
    {
        $this->attrFormats = array_replace($this->attrFormats, $formats);

        return $this;
    }
}
