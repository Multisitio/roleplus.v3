import os
with open('web/css/editor.min.css', 'a') as f:
    f.write('\nnav[data-toolbar="pinceles"] button[data-tag="delete_parent"], nav[data-toolbar="pinceles"] button[data-tag="delete_node"], nav[data-toolbar="pinceles"] button[data-tag="delete_parent"] svg, nav[data-toolbar="pinceles"] button[data-tag="delete_node"] svg { color: #e60000; stroke: #e60000; }\n')
